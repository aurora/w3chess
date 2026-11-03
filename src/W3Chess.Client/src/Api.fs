module W3Chess.Client.Api

open Fable.Core
open Fable.Core.JsInterop
open Browser.WebStorage
open W3Chess.Domain
open W3Chess.Contracts

// The client talks to the Fable.Remoting server with plain JSON over fetch and
// decodes responses explicitly. This avoids Fable.SimpleJson's reflection-based
// decoding, which misclassifies F# options and lists under Fable 5.

[<Emit("fetch($0, { method: 'POST', headers: { 'Content-Type': 'application/json; charset=utf-8' }, body: $1 })")>]
let private fetchPost (url: string) (body: string) : JS.Promise<obj> = jsNative

[<Emit("$0.text()")>]
let private responseText (response: obj) : JS.Promise<string> = jsNative

[<Emit("$0.ok")>]
let private responseOk (response: obj) : bool = jsNative

[<Emit("$0.status")>]
let private responseStatus (response: obj) : int = jsNative

[<Emit("JSON.stringify($0)")>]
let private jsonStringify (value: obj) : string = jsNative

[<Emit("JSON.parse($0)")>]
let private jsonParse (text: string) : obj = jsNative

let private post (name: string) (args: obj[]) : Async<string> =
    async {
        let url = "/api/IW3ChessApi/" + name
        let body = jsonStringify (box args)
        let! response = fetchPost url body |> Async.AwaitPromise
        let! text = responseText response |> Async.AwaitPromise

        if not (responseOk response) then
            return failwithf "HTTP %d: %s" (responseStatus response) text
        else
            return text
    }

let private optValue (value: obj) : 'a option =
    if isNull value then None else Some(unbox value)

let private optString (value: string option) : obj =
    match value with
    | Some text -> box text
    | None -> null

let private encodeColorChoice =
    function
    | ColorChoice.AsWhite -> "AsWhite"
    | ColorChoice.AsBlack -> "AsBlack"
    | ColorChoice.RandomColor -> "RandomColor"

let private encodePieceKind =
    function
    | Pawn -> "Pawn"
    | Knight -> "Knight"
    | Bishop -> "Bishop"
    | Rook -> "Rook"
    | Queen -> "Queen"
    | King -> "King"

let private optPieceKind (value: PieceKind option) : obj =
    match value with
    | Some kind -> box (encodePieceKind kind)
    | None -> null

let private colorOfString (value: string) =
    if value = "Black" then Color.Black else Color.White

let private statusOfString (value: string) =
    match value with
    | "Waiting" -> GameStatus.Waiting
    | "Finished" -> GameStatus.Finished
    | _ -> GameStatus.Active

let private decodeCredentials (json: obj) : PlayerCredentials =
    { GameId = unbox json?GameId
      PlayerId = unbox json?PlayerId
      Token = unbox json?Token
      Color = colorOfString (unbox json?Color) }

let private decodeResult (json: obj) : Result<PlayerCredentials, string> =
    let ok = json?Ok
    if isNull ok then Error(unbox json?Error) else Ok(decodeCredentials ok)

let private decodePlayerView (json: obj) : PlayerView =
    { Nickname = unbox json?Nickname
      Color = colorOfString (unbox json?Color) }

let private decodeMoveView (json: obj) : MoveView =
    { Ply = unbox json?Ply
      San = unbox json?San
      Uci = unbox json?Uci
      FromSquare = unbox json?FromSquare
      ToSquare = unbox json?ToSquare
      Message = optValue json?Message }

let private decodeGameView (json: obj) : GameView =
    { GameId = unbox json?GameId
      White = optValue json?White |> Option.map decodePlayerView
      Black = optValue json?Black |> Option.map decodePlayerView
      SideToMove = colorOfString (unbox json?SideToMove)
      Status = statusOfString (unbox json?Status)
      Result = optValue json?Result
      Fen = unbox json?Fen
      Moves = (unbox<obj[]> json?Moves) |> Array.map decodeMoveView
      Message = optValue json?Message
      DrawOfferPending = unbox json?DrawOfferPending
      DrawOfferBy = optValue json?DrawOfferBy }

let private decodeOpenGame (json: obj) : OpenGame =
    { GameId = unbox json?GameId
      Creator = unbox json?Creator
      CreatedAt = unbox json?CreatedAt
      Message = optValue json?Message }

let private decodeArchiveGame (json: obj) : ArchiveGame =
    { GameId = unbox json?GameId
      White = optValue json?White
      Black = optValue json?Black
      Status = statusOfString (unbox json?Status)
      Result = optValue json?Result
      UpdatedAt = unbox json?UpdatedAt }

let private decodeAccount (json: obj) : AccountInfo =
    { Email = unbox json?Email
      Nickname = unbox json?Nickname
      Language = unbox json?Language }

let private encodeAccount (account: AccountInfo) : obj =
    createObj [
        "Email" ==> account.Email
        "Nickname" ==> account.Nickname
        "Language" ==> account.Language
    ]

let private decodeAction (json: obj) : ActionResponse =
    { Ok = unbox json?Ok
      Error = optValue json?Error }

let listOpenGames () : Async<OpenGame[]> =
    async {
        let! text = post "ListOpenGames" [||]
        return (unbox<obj[]> (jsonParse text)) |> Array.map decodeOpenGame
    }

let listGamesForEmail (email: string) : Async<ArchiveGame[]> =
    async {
        let! text = post "ListGamesForEmail" [| box email |]
        return (unbox<obj[]> (jsonParse text)) |> Array.map decodeArchiveGame
    }

let requestLogin (request: LoginRequest) : Async<ActionResponse> =
    async {
        let body =
            createObj [
                "Email" ==> request.Email
                "Language" ==> request.Language
            ]

        let! text = post "RequestLogin" [| body |]
        return decodeAction (jsonParse text)
    }

let completeLogin (token: string) : Async<Result<AccountSession, string>> =
    async {
        let! text = post "CompleteLogin" [| box token |]
        let json = jsonParse text
        let ok = json?Ok

        if isNull ok then
            return Error(unbox json?Error)
        else
            return
                Ok
                    { Session = unbox ok?Session
                      Account = decodeAccount ok?Account }
    }

let getAccount (session: string) : Async<AccountInfo option> =
    async {
        let! text = post "GetAccount" [| box session |]
        let json = jsonParse text
        return if isNull json then None else Some(decodeAccount json)
    }

let createGame (request: CreateGameRequest) : Async<Result<PlayerCredentials, string>> =
    async {
        let body =
            createObj [
                "Nickname" ==> request.Nickname
                "Email" ==> request.Email
                "ColorChoice" ==> encodeColorChoice request.ColorChoice
                "OpponentNickname" ==> optString request.OpponentNickname
                "OpponentEmail" ==> optString request.OpponentEmail
                "Message" ==> optString request.Message
                "Language" ==> request.Language
                "Session" ==> optString request.Session
            ]

        let! text = post "CreateGame" [| body |]
        return decodeResult (jsonParse text)
    }

let joinGame (request: JoinGameRequest) : Async<Result<PlayerCredentials, string>> =
    async {
        let body =
            createObj [
                "GameId" ==> request.GameId
                "Nickname" ==> request.Nickname
                "Email" ==> request.Email
                "Message" ==> optString request.Message
                "Language" ==> request.Language
                "Session" ==> optString request.Session
            ]

        let! text = post "JoinGame" [| body |]
        return decodeResult (jsonParse text)
    }

let getGame (gameId: string) : Async<GameView option> =
    async {
        let! text = post "GetGame" [| box gameId |]
        let json = jsonParse text
        return if isNull json then None else Some(decodeGameView json)
    }

let validateToken (gameId: string) (token: string) : Async<PlayerCredentials option> =
    async {
        let! text = post "ValidateToken" [| box gameId; box token |]
        let json = jsonParse text
        return if isNull json then None else Some(decodeCredentials json)
    }

let validateSession (gameId: string) (session: string) : Async<PlayerCredentials option> =
    async {
        let! text = post "ValidateSession" [| box gameId; box session |]
        let json = jsonParse text
        return if isNull json then None else Some(decodeCredentials json)
    }

let submitMove (request: SubmitMoveRequest) : Async<ActionResponse> =
    async {
        let body =
            createObj [
                "GameId" ==> request.GameId
                "PlayerId" ==> request.PlayerId
                "Token" ==> request.Token
                "FromSquare" ==> box request.FromSquare
                "ToSquare" ==> box request.ToSquare
                "Promotion" ==> optPieceKind request.Promotion
                "Message" ==> optString request.Message
                "Session" ==> optString request.Session
            ]

        let! text = post "SubmitMove" [| body |]
        return decodeAction (jsonParse text)
    }

let private action (name: string) (request: ActionRequest) : Async<ActionResponse> =
    async {
        let body =
            createObj [
                "GameId" ==> request.GameId
                "PlayerId" ==> request.PlayerId
                "Token" ==> request.Token
                "Message" ==> optString request.Message
                "Session" ==> optString request.Session
            ]

        let! text = post name [| body |]
        return decodeAction (jsonParse text)
    }

let resign = action "Resign"
let offerDraw = action "OfferDraw"
let acceptDraw = action "AcceptDraw"

let requestDigest (request: DigestRequest) : Async<ActionResponse> =
    async {
        let body =
            createObj [
                "Email" ==> request.Email
                "Language" ==> request.Language
            ]

        let! text = post "RequestDigest" [| body |]
        return decodeAction (jsonParse text)
    }

[<Emit("new EventSource($0)")>]
let private createEventSource (url: string) : obj = jsNative

[<Emit("$0.onmessage = (e) => { $1(e.data); }")>]
let private onEventSourceMessage (source: obj) (handler: string -> unit) : unit = jsNative

[<Emit("$0.close()")>]
let private closeEventSource (source: obj) : unit = jsNative

/// Subscribes to live updates for a game, using the per-game token from the
/// email link or the account session. The server validates whichever is given.
let subscribeGame
    (gameId: string)
    (token: string option)
    (session: string option)
    (onUpdate: unit -> unit)
    : System.IDisposable =
    match token, session with
    | None, None ->
        { new System.IDisposable with
            member _.Dispose() = () }
    | _ ->
        let query =
            match token, session with
            | Some value, _ -> sprintf "?token=%s" value
            | None, Some value -> sprintf "?session=%s" value
            | None, None -> ""

        let source = createEventSource (sprintf "/api/game/%s/events%s" gameId query)
        onEventSourceMessage source (fun _ -> onUpdate ())

        { new System.IDisposable with
            member _.Dispose() = closeEventSource source }

let private sessionKey = "w3chess.session"
let private accountKey = "w3chess.account"

let currentSession () : string option =
    let value = localStorage.getItem sessionKey
    if isNull value || value = "" then None else Some value

let currentAccount () : AccountInfo option =
    let value = localStorage.getItem accountKey

    if isNull value || value = "" then
        None
    else
        try
            Some(decodeAccount (jsonParse value))
        with _ ->
            None

let saveSession (session: AccountSession) =
    localStorage.setItem (sessionKey, session.Session)
    localStorage.setItem (accountKey, jsonStringify (encodeAccount session.Account))

let clearSession () =
    localStorage.removeItem sessionKey
    localStorage.removeItem accountKey

let private storageKey (gameId: string) = "w3chess.credentials." + gameId

let saveCredentials (credentials: PlayerCredentials) =
    let color =
        match credentials.Color with
        | White -> "w"
        | Black -> "b"

    localStorage.setItem(
        storageKey credentials.GameId,
        String.concat "|" [ credentials.GameId; credentials.PlayerId; credentials.Token; color ]
    )

let loadCredentials (gameId: string) : PlayerCredentials option =
    let value = localStorage.getItem (storageKey gameId)

    if isNull value then
        None
    else
        match value.Split('|') with
        | parts when parts.Length = 4 && parts.[0] = gameId ->
            Some
                { GameId = parts.[0]
                  PlayerId = parts.[1]
                  Token = parts.[2]
                  Color = (if parts.[3] = "b" then Black else White) }
        | _ -> None
