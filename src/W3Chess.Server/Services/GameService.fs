namespace W3Chess.Server.Services

open System
open Microsoft.Data.Sqlite
open W3Chess.Domain
open W3Chess.Contracts
open W3Chess.Server
open W3Chess.Server.Storage

/// Application logic for creating, joining and playing correspondence games.
type GameService(settings: AppSettings, authService: AuthService, notifier: Notifier, gameEvents: GameEvents) =

    let connect () = Db.connect settings.DatabasePath

    let colorToChar = function
        | Color.White -> "w"
        | Color.Black -> "b"

    let colorOfChar (value: string) =
        if value = "b" then Color.Black else Color.White

    let newGameId () =
        let alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"
        let random = Random()
        "W3C-" + String(Array.init 6 (fun _ -> alphabet.[random.Next(alphabet.Length)]))

    let validateInput (nickname: string) (email: string) =
        if String.IsNullOrWhiteSpace nickname then Error "Nickname is required"
        elif nickname.Contains ":" then Error "Nickname must not contain ':'"
        elif String.IsNullOrWhiteSpace email || not (email.Contains "@") then Error "A valid email is required"
        else Ok()

    let sendMail (address: string) subject body =
        notifier.Send { To = address; Subject = subject; Body = body }

    /// Sends a game mail; when the recipient has an account the body gains a
    /// single-use login link that lands back on `next` after the login.
    let sendGameMail (email: string) (language: string) (next: string) subject body =
        async {
            let! loginLink = authService.CreateLoginLink(email, next)

            let body =
                match loginLink with
                | Some url -> body + "\n\n" + EmailTemplates.loginLink language url
                | None -> body

            do! sendMail email subject body
        }

    let legacyBoardOf (fen: string) = Board.toLegacyBoard (Board.ofFen fen).Board

    let playerView (connection: SqliteConnection) (gamePlayer: GamePlayerRow) =
        PlayerRepository.findById connection gamePlayer.PlayerId
        |> Option.map (fun player ->
            { Nickname = player.Nickname
              Color = colorOfChar gamePlayer.Color })

    let buildView (connection: SqliteConnection) (game: GameRow) : GameView =
        let players = GamePlayerRepository.listByGame connection game.Id
        let moves = MoveRepository.listByGame connection game.Id

        let white =
            players
            |> List.tryFind (fun gp -> gp.Color = "w")
            |> Option.bind (playerView connection)

        let black =
            players
            |> List.tryFind (fun gp -> gp.Color = "b")
            |> Option.bind (playerView connection)

        let moveViews =
            moves
            |> List.map (fun move ->
                let parsed = Notation.ofUci move.Uci

                { Ply = move.Ply
                  San = move.San
                  Uci = move.Uci
                  FromSquare = (match parsed with Some m -> m.From | None -> 0)
                  ToSquare = (match parsed with Some m -> m.To | None -> 0)
                  Message = (if move.Message = "" then None else Some move.Message) })
            |> List.toArray

        let status =
            match game.Status with
            | "waiting" -> GameStatus.Waiting
            | "active" -> GameStatus.Active
            | _ -> GameStatus.Finished

        { GameId = game.Id
          White = white
          Black = black
          SideToMove = colorOfChar game.SideToMove
          Status = status
          Result = (if game.Result = "" then None else Some game.Result)
          Fen = game.CurrentFen
          Moves = moveViews
          Message = (if game.LastMessage = "" then None else Some game.LastMessage)
          DrawOfferPending = game.DrawOfferBy <> ""
          DrawOfferBy = (if game.DrawOfferBy = "" then None else Some game.DrawOfferBy) }

    let otherPlayer (players: GamePlayerRow list) (playerId: string) =
        players |> List.tryFind (fun gp -> gp.PlayerId <> playerId)

    let accountOf (connection: SqliteConnection) (session: string option) =
        session
        |> Option.bind (SessionService.validate settings.TokenSecret)
        |> Option.bind (fun accountId -> AccountRepository.findById connection accountId)

    member _.CreateGame(request: CreateGameRequest) : Async<Result<PlayerCredentials, string>> =
        async {
            let accountOk =
                not settings.RequireAccount
                || (request.Session |> Option.bind (SessionService.validate settings.TokenSecret)).IsSome

            match validateInput request.Nickname request.Email with
            | Error message -> return Error message
            | Ok() when not accountOk -> return Error "An account is required to create games"
            | Ok() ->
                use connection = connect ()
                let now = Db.nowIso ()
                let creatorId = Db.newId ()
                let gameId = newGameId ()

                let creatorColor =
                    match request.ColorChoice with
                    | ColorChoice.AsWhite -> Color.White
                    | ColorChoice.AsBlack -> Color.Black
                    | ColorChoice.RandomColor -> if Random().Next(2) = 0 then Color.White else Color.Black

                PlayerRepository.insert
                    connection
                    { Id = creatorId
                      Nickname = request.Nickname
                      Email = request.Email
                      Language = request.Language
                      CreatedAt = now }

                let opponentId =
                    match request.OpponentNickname, request.OpponentEmail with
                    | Some nickname, Some email when
                        not (String.IsNullOrWhiteSpace nickname)
                        && not (String.IsNullOrWhiteSpace email)
                        ->
                        let id = Db.newId ()

                        PlayerRepository.insert
                            connection
                            { Id = id
                              Nickname = nickname
                              Email = email
                              Language = request.Language
                              CreatedAt = now }

                        Some id
                    | _ -> None

                let initialFen = Board.startFen
                let isActive = opponentId.IsSome

                GameRepository.insert
                    connection
                    { Id = gameId
                      Status = (if isActive then "active" else "waiting")
                      Result = ""
                      InitialFen = initialFen
                      CurrentFen = initialFen
                      SideToMove = "w"
                      MoveCount = 0
                      CreatedAt = now
                      UpdatedAt = now
                      LastMessage = (defaultArg request.Message "")
                      DrawOfferBy = "" }

                GamePlayerRepository.insert
                    connection
                    { GameId = gameId
                      PlayerId = creatorId
                      Color = colorToChar creatorColor
                      TokenSecret = Db.newId ()
                      NotifyByEmail = 1 }

                match opponentId with
                | Some id ->
                    GamePlayerRepository.insert
                        connection
                        { GameId = gameId
                          PlayerId = id
                          Color = colorToChar (Color.opposite creatorColor)
                          TokenSecret = Db.newId ()
                          NotifyByEmail = 1 }
                | None -> ()

                let creatorToken = TokenService.create settings.TokenSecret gameId creatorId
                let boardText = legacyBoardOf initialFen

                let subject, body =
                    EmailTemplates.confirmation
                        request.Language
                        settings.PublicBaseUrl
                        gameId
                        creatorToken
                        boardText

                do! sendGameMail request.Email request.Language (sprintf "game/%s" gameId) subject body

                match opponentId with
                | Some id ->
                    match PlayerRepository.findById connection id with
                    | Some opponent ->
                        let opponentToken =
                            TokenService.create settings.TokenSecret gameId opponent.Id

                        let subject, body =
                            EmailTemplates.invitation
                                opponent.Language
                                settings.PublicBaseUrl
                                gameId
                                opponentToken
                                request.Nickname
                                opponent.Nickname
                                boardText

                        do! sendGameMail opponent.Email opponent.Language (sprintf "game/%s" gameId) subject body
                    | None -> ()
                | None -> ()

                return
                    Ok
                        { GameId = gameId
                          PlayerId = creatorId
                          Token = creatorToken
                          Color = creatorColor }
        }

    member _.JoinGame(request: JoinGameRequest) : Async<Result<PlayerCredentials, string>> =
        async {
            let accountOk =
                not settings.RequireAccount
                || (request.Session |> Option.bind (SessionService.validate settings.TokenSecret)).IsSome

            match validateInput request.Nickname request.Email with
            | Error message -> return Error message
            | Ok() when not accountOk -> return Error "An account is required to join games"
            | Ok() ->
                use connection = connect ()

                match GameRepository.findById connection request.GameId with
                | None -> return Error "Game not found"
                | Some game when game.Status <> "waiting" -> return Error "This game is not open for joining"
                | Some game ->
                    let now = Db.nowIso ()
                    let playerId = Db.newId ()

                    PlayerRepository.insert
                        connection
                        { Id = playerId
                          Nickname = request.Nickname
                          Email = request.Email
                          Language = request.Language
                          CreatedAt = now }

                    // The joiner takes the colour opposite to the creator, who is
                    // the only player of a waiting game. Side-to-move of a fresh
                    // game is always White regardless of the colours chosen.
                    let color =
                        GamePlayerRepository.listByGame connection game.Id
                        |> List.tryHead
                        |> Option.map (fun gp -> Color.opposite (colorOfChar gp.Color))
                        |> Option.defaultValue Color.White

                    GamePlayerRepository.insert
                        connection
                        { GameId = game.Id
                          PlayerId = playerId
                          Color = colorToChar color
                          TokenSecret = Db.newId ()
                          NotifyByEmail = 1 }

                    GameRepository.update
                        connection
                        { game with
                            Status = "active"
                            UpdatedAt = now
                            LastMessage = (defaultArg request.Message game.LastMessage) }

                    gameEvents.Publish(game.Id, "updated")

                    let playerToken = TokenService.create settings.TokenSecret game.Id playerId
                    let boardText = legacyBoardOf game.CurrentFen

                    let subject, body =
                        EmailTemplates.confirmation
                            request.Language
                            settings.PublicBaseUrl
                            game.Id
                            playerToken
                            boardText

                    do! sendGameMail request.Email request.Language (sprintf "game/%s" game.Id) subject body

                    let creator =
                        GamePlayerRepository.listByGame connection game.Id
                        |> List.tryFind (fun gp -> gp.PlayerId <> playerId)
                        |> Option.bind (fun gp -> PlayerRepository.findById connection gp.PlayerId)

                    match creator with
                    | Some creator ->
                        let creatorToken =
                            TokenService.create settings.TokenSecret game.Id creator.Id

                        let subject, body =
                            EmailTemplates.joined
                                creator.Language
                                settings.PublicBaseUrl
                                game.Id
                                creatorToken
                                request.Nickname
                                boardText

                        do! sendGameMail creator.Email creator.Language (sprintf "game/%s" game.Id) subject body
                    | None -> ()

                    return
                        Ok
                            { GameId = game.Id
                              PlayerId = playerId
                              Token = playerToken
                              Color = color }
        }

    member _.GetGame(gameId: string) : Async<GameView option> =
        async {
            use connection = connect ()

            return
                GameRepository.findById connection gameId
                |> Option.map (buildView connection)
        }

    member _.ValidateToken(gameId: string, token: string) : Async<PlayerCredentials option> =
        async {
            use connection = connect ()

            match TokenService.validate settings.TokenSecret gameId token with
            | None -> return None
            | Some playerId ->
                return
                    GamePlayerRepository.findByGameAndPlayer connection gameId playerId
                    |> Option.map (fun gamePlayer ->
                        { GameId = gameId
                          PlayerId = playerId
                          Token = token
                          Color = colorOfChar gamePlayer.Color })
        }

    /// Resolves an account session into this game's player, mirroring
    /// `ValidateToken` for logged-in players.
    member _.ValidateSession(gameId: string, session: string) : Async<PlayerCredentials option> =
        async {
            use connection = connect ()

            return
                accountOf connection (Some session)
                |> Option.bind (fun account ->
                    GamePlayerRepository.findByGameAndEmail connection gameId account.Email)
                |> Option.map (fun gamePlayer ->
                    { GameId = gameId
                      PlayerId = gamePlayer.PlayerId
                      Token = ""
                      Color = colorOfChar gamePlayer.Color })
        }

    member _.ListOpenGames() : Async<OpenGame[]> =
        async {
            use connection = connect ()

            return
                GameRepository.listOpen connection
                |> List.choose (fun game ->
                    let creator =
                        GamePlayerRepository.listByGame connection game.Id
                        |> List.tryHead
                        |> Option.bind (fun gp -> PlayerRepository.findById connection gp.PlayerId)

                    creator
                    |> Option.map (fun player ->
                        { GameId = game.Id
                          Creator = player.Nickname
                          CreatedAt = game.CreatedAt
                          Message = (if game.LastMessage = "" then None else Some game.LastMessage) }))
                |> Array.ofList
        }

    member _.ListGamesForEmail(email: string) : Async<ArchiveGame[]> =
        async {
            use connection = connect ()

            return
                GameRepository.listForEmail connection email
                |> List.map (fun game ->
                    let players = GamePlayerRepository.listByGame connection game.Id

                    let nickname color =
                        players
                        |> List.tryFind (fun gp -> gp.Color = color)
                        |> Option.bind (fun gp -> PlayerRepository.findById connection gp.PlayerId)
                        |> Option.map (fun player -> player.Nickname)

                    let status =
                        match game.Status with
                        | "waiting" -> GameStatus.Waiting
                        | "active" -> GameStatus.Active
                        | _ -> GameStatus.Finished

                    { GameId = game.Id
                      White = nickname "w"
                      Black = nickname "b"
                      Status = status
                      Result = (if game.Result = "" then None else Some game.Result)
                      UpdatedAt = game.UpdatedAt })
                |> Array.ofList
        }

    /// Validates credentials (per-game token or account session) and returns the
    /// game, the acting game player and an open connection.
    member private _.Authorise(gameId: string, playerId: string, token: string, session: string option) =
        let connection = connect ()

        let withPlayer gamePlayer =
            match GameRepository.findById connection gameId with
            | None ->
                connection.Dispose()
                Error "Game not found"
            | Some game -> Ok(connection, game, gamePlayer)

        match TokenService.validate settings.TokenSecret gameId token with
        | Some validPlayer when validPlayer = playerId ->
            match GamePlayerRepository.findByGameAndPlayer connection gameId playerId with
            | None ->
                connection.Dispose()
                Error "You are not a player in this game"
            | Some gamePlayer -> withPlayer gamePlayer
        | _ ->
            match accountOf connection session with
            | Some account ->
                match GamePlayerRepository.findByGameAndEmail connection gameId account.Email with
                | Some gamePlayer -> withPlayer gamePlayer
                | None ->
                    connection.Dispose()
                    Error "You are not a player in this game"
            | None ->
                connection.Dispose()
                Error "Invalid access token"

    member this.SubmitMove(request: SubmitMoveRequest) : Async<ActionResponse> =
        async {
            match this.Authorise(request.GameId, request.PlayerId, request.Token, request.Session) with
            | Error message -> return { Ok = false; Error = Some message }
            | Ok(connection, game, gamePlayer) ->
                use connection = connection

                if game.Status <> "active" then
                    return { Ok = false; Error = Some "The game is not active" }
                elif gamePlayer.Color <> game.SideToMove then
                    return { Ok = false; Error = Some "It is not your turn" }
                else
                    let position = Board.ofFen game.CurrentFen

                    let move =
                        { From = request.FromSquare
                          To = request.ToSquare
                          Promotion = request.Promotion }

                    if not (MoveGen.isLegalMove position move) then
                        return { Ok = false; Error = Some "Illegal move" }
                    else
                        let next = MoveGen.applyMove position move
                        let record = Notation.toRecord position move
                        let players = GamePlayerRepository.listByGame connection request.GameId
                        let opponent = otherPlayer players request.PlayerId
                        let now = Db.nowIso ()

                        // Replay the game from the initial FEN through every
                        // stored move plus the new one; repetition detection
                        // needs the positions of the whole game.
                        let storedMoves = MoveRepository.listByGame connection request.GameId

                        let history =
                            storedMoves
                            |> List.scan
                                (fun position stored ->
                                    match Notation.ofUci stored.Uci with
                                    | Some storedMove -> MoveGen.applyMove position storedMove
                                    | None -> position)
                                (Board.ofFen game.InitialFen)

                        let history = history @ [ next ]

                        let finishedResult =
                            match Rules.status next with
                            | Rules.Checkmate -> Some(if gamePlayer.Color = "w" then "1-0" else "0-1")
                            | Rules.Stalemate
                            | Rules.Draw _ -> Some "1/2-1/2"
                            | _ when Rules.isThreefoldRepetition history -> Some "1/2-1/2"
                            | _ -> None

                        MoveRepository.insert
                            connection
                            { GameId = request.GameId
                              Ply = game.MoveCount + 1
                              Uci = Notation.toUci move
                              San = record.San
                              FenAfter = Board.toFen next
                              Message = (defaultArg request.Message "")
                              CreatedAt = now }

                        let updated =
                            { game with
                                CurrentFen = Board.toFen next
                                SideToMove = colorToChar next.SideToMove
                                MoveCount = game.MoveCount + 1
                                UpdatedAt = now
                                Status = (if finishedResult.IsSome then "finished" else game.Status)
                                Result = (defaultArg finishedResult game.Result)
                                LastMessage = (defaultArg request.Message "")
                                DrawOfferBy = "" }

                        GameRepository.update connection updated

                        gameEvents.Publish(request.GameId, "updated")

                        let boardText = legacyBoardOf updated.CurrentFen

                        match opponent with
                        | Some opponentGamePlayer ->
                            match PlayerRepository.findById connection opponentGamePlayer.PlayerId with
                            | Some opponentPlayer ->
                                let opponentToken =
                                    TokenService.create settings.TokenSecret request.GameId opponentPlayer.Id

                                let subject, body =
                                    match finishedResult with
                                    | Some result ->
                                        EmailTemplates.finished
                                            opponentPlayer.Language
                                            settings.PublicBaseUrl
                                            request.GameId
                                            opponentToken
                                            result
                                    | None ->
                                        EmailTemplates.yourTurn
                                            opponentPlayer.Language
                                            settings.PublicBaseUrl
                                            request.GameId
                                            opponentToken
                                            opponentPlayer.Nickname
                                            record.San
                                            boardText

                                do! sendGameMail opponentPlayer.Email opponentPlayer.Language (sprintf "game/%s" request.GameId) subject body
                            | None -> ()
                        | None -> ()

                        match PlayerRepository.findById connection request.PlayerId with
                        | Some moverPlayer ->
                            let moverToken =
                                TokenService.create settings.TokenSecret request.GameId moverPlayer.Id

                            let subject, body =
                                match finishedResult with
                                | Some result ->
                                    EmailTemplates.finished
                                        moverPlayer.Language
                                        settings.PublicBaseUrl
                                        request.GameId
                                        moverToken
                                        result
                                | None ->
                                    EmailTemplates.moveCopy
                                        moverPlayer.Language
                                        settings.PublicBaseUrl
                                        request.GameId
                                        moverToken
                                        record.San
                                        boardText

                            do! sendGameMail moverPlayer.Email moverPlayer.Language (sprintf "game/%s" request.GameId) subject body
                        | None -> ()

                        return { Ok = true; Error = None }
        }

    member this.Resign(request: ActionRequest) : Async<ActionResponse> =
        async {
            match this.Authorise(request.GameId, request.PlayerId, request.Token, request.Session) with
            | Error message -> return { Ok = false; Error = Some message }
            | Ok(connection, game, gamePlayer) ->
                use connection = connection

                if game.Status <> "active" then
                    return { Ok = false; Error = Some "The game is not active" }
                else
                    let now = Db.nowIso ()
                    let result = if gamePlayer.Color = "w" then "0-1" else "1-0"

                    GameRepository.update
                        connection
                        { game with
                            Status = "finished"
                            Result = result
                            UpdatedAt = now
                            DrawOfferBy = ""
                            LastMessage = (defaultArg request.Message game.LastMessage) }

                    gameEvents.Publish(request.GameId, "updated")

                    let players = GamePlayerRepository.listByGame connection request.GameId

                    for gp in players do
                        match PlayerRepository.findById connection gp.PlayerId with
                        | Some player ->
                            let token = TokenService.create settings.TokenSecret request.GameId player.Id

                            let subject, body =
                                EmailTemplates.finished
                                    player.Language
                                    settings.PublicBaseUrl
                                    request.GameId
                                    token
                                    result

                            do! sendGameMail player.Email player.Language (sprintf "game/%s" request.GameId) subject body
                        | None -> ()

                    return { Ok = true; Error = None }
        }

    member this.OfferDraw(request: ActionRequest) : Async<ActionResponse> =
        async {
            match this.Authorise(request.GameId, request.PlayerId, request.Token, request.Session) with
            | Error message -> return { Ok = false; Error = Some message }
            | Ok(connection, game, gamePlayer) ->
                use connection = connection

                if game.Status <> "active" then
                    return { Ok = false; Error = Some "The game is not active" }
                else
                    GameRepository.update
                        connection
                        { game with
                            DrawOfferBy = gamePlayer.PlayerId
                            UpdatedAt = Db.nowIso()
                            LastMessage = (defaultArg request.Message game.LastMessage) }

                    gameEvents.Publish(request.GameId, "updated")

                    let players = GamePlayerRepository.listByGame connection request.GameId

                    match otherPlayer players gamePlayer.PlayerId with
                    | Some opponentGamePlayer ->
                        match PlayerRepository.findById connection opponentGamePlayer.PlayerId with
                        | Some opponentPlayer ->
                            let opponentToken =
                                TokenService.create settings.TokenSecret request.GameId opponentPlayer.Id

                            let subject, body =
                                EmailTemplates.drawOffer
                                    opponentPlayer.Language
                                    settings.PublicBaseUrl
                                    request.GameId
                                    opponentToken
                                    opponentPlayer.Nickname
                                    (legacyBoardOf game.CurrentFen)

                            do! sendGameMail opponentPlayer.Email opponentPlayer.Language (sprintf "game/%s" request.GameId) subject body
                        | None -> ()
                    | None -> ()

                    return { Ok = true; Error = None }
        }

    member this.AcceptDraw(request: ActionRequest) : Async<ActionResponse> =
        async {
            match this.Authorise(request.GameId, request.PlayerId, request.Token, request.Session) with
            | Error message -> return { Ok = false; Error = Some message }
            | Ok(connection, game, gamePlayer) ->
                use connection = connection

                if game.Status <> "active" then
                    return { Ok = false; Error = Some "The game is not active" }
                elif game.DrawOfferBy = "" || game.DrawOfferBy = gamePlayer.PlayerId then
                    return { Ok = false; Error = Some "There is no draw offer to accept" }
                else
                    GameRepository.update
                        connection
                        { game with
                            Status = "finished"
                            Result = "1/2-1/2"
                            UpdatedAt = Db.nowIso()
                            DrawOfferBy = "" }

                    gameEvents.Publish(request.GameId, "updated")

                    let players = GamePlayerRepository.listByGame connection request.GameId

                    for gp in players do
                        match PlayerRepository.findById connection gp.PlayerId with
                        | Some player ->
                            let token = TokenService.create settings.TokenSecret request.GameId player.Id

                            let subject, body =
                                EmailTemplates.finished
                                    player.Language
                                    settings.PublicBaseUrl
                                    request.GameId
                                    token
                                    "1/2-1/2"

                            do! sendGameMail player.Email player.Language (sprintf "game/%s" request.GameId) subject body
                        | None -> ()

                    return { Ok = true; Error = None }
        }

    member _.RequestDigest(request: DigestRequest) : Async<ActionResponse> =
        async {
            use connection = connect ()
            let games = GameRepository.listForEmail connection request.Email

            let entries =
                games
                |> List.choose (fun game ->
                    let players = GamePlayerRepository.listByGame connection game.Id

                    let matching =
                        players
                        |> List.tryFind (fun gp ->
                            match PlayerRepository.findById connection gp.PlayerId with
                            | Some player ->
                                player.Email.ToLowerInvariant() = request.Email.ToLowerInvariant()
                            | None -> false)

                    matching
                    |> Option.map (fun gp ->
                        let token = TokenService.create settings.TokenSecret game.Id gp.PlayerId

                        let statusText =
                            match game.Status, game.Result with
                            | "waiting", _ -> "waiting"
                            | "finished", result -> sprintf "finished %s" result
                            | _, _ -> "active"

                        sprintf
                            "%s — %s — %s"
                            game.Id
                            statusText
                            (EmailTemplates.link settings.PublicBaseUrl game.Id token)))

            let subject, body = EmailTemplates.digest request.Language entries

            do! sendGameMail request.Email request.Language "" subject body

            return { Ok = true; Error = None }
        }
