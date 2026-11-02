namespace W3Chess.Server.Services

open System
open System.IO
open Microsoft.Data.Sqlite
open W3Chess.Domain
open W3Chess.Server.Storage

/// Imports legacy w3chess text files into the relational store.
module ImportService =
    let private resultText =
        function
        | LegacyResult.WhiteWins -> "1-0"
        | LegacyResult.BlackWins -> "0-1"
        | LegacyResult.Drawn -> "1/2-1/2"
        | LegacyResult.StillPlaying -> ""

    let private importFile (connection: SqliteConnection) (path: string) : Result<unit, string> =
        let fileName = Path.GetFileName path

        if fileName = "adm.pss" || fileName.StartsWith "." then
            Ok()
        else
            match GameRepository.findById connection fileName with
            | Some _ -> Ok()
            | None ->
                let isOpen = fileName.StartsWith "X"
                let content = File.ReadAllText path

                match LegacyImport.parseContent fileName isOpen content with
                | Error error -> Error(sprintf "%s: %s" fileName error.Message)
                | Ok game ->
                    let now = Db.nowIso ()

                    let insertPlayer (legacyPlayer: LegacyPlayer) =
                        if String.IsNullOrWhiteSpace legacyPlayer.Nickname
                           && String.IsNullOrWhiteSpace legacyPlayer.Email then
                            None
                        else
                            let id = Db.newId ()

                            PlayerRepository.insert
                                connection
                                { Id = id
                                  Nickname = legacyPlayer.Nickname
                                  Email = legacyPlayer.Email
                                  Language = "en"
                                  CreatedAt = now }

                            Some id

                    let whiteId = insertPlayer game.White
                    let blackId = insertPlayer game.Black

                    let status =
                        if game.Result <> LegacyResult.StillPlaying then "finished"
                        elif game.IsOpen then "waiting"
                        else "active"

                    GameRepository.insert
                        connection
                        { Id = fileName
                          Status = status
                          Result = resultText game.Result
                          InitialFen = Board.startFen
                          CurrentFen = Board.toFen game.FinalPosition
                          SideToMove = (if game.SideToMove = Color.White then "w" else "b")
                          MoveCount = List.length game.MoveRecords
                          CreatedAt = now
                          UpdatedAt = now
                          LastMessage = (defaultArg game.Message "")
                          DrawOfferBy = "" }

                    let addGamePlayer id color =
                        match id with
                        | Some playerId ->
                            GamePlayerRepository.insert
                                connection
                                { GameId = fileName
                                  PlayerId = playerId
                                  Color = color
                                  TokenSecret = Db.newId ()
                                  NotifyByEmail = 1 }
                        | None -> ()

                    addGamePlayer whiteId "w"
                    addGamePlayer blackId "b"

                    let rec writeMoves position ply records =
                        match records with
                        | [] -> ()
                        | record :: rest ->
                            let next = MoveGen.applyMove position record.Move

                            MoveRepository.insert
                                connection
                                { GameId = fileName
                                  Ply = ply
                                  Uci = Notation.toUci record.Move
                                  San = record.San
                                  FenAfter = Board.toFen next
                                  Message = ""
                                  CreatedAt = now }

                            writeMoves next (ply + 1) rest

                    writeMoves Board.startPosition 1 game.MoveRecords

                    Ok()

    /// Imports every file in `directory`; returns a list of warnings/errors.
    let importDirectory (connection: SqliteConnection) (directory: string) : string list =
        if not (Directory.Exists directory) then
            [ sprintf "Legacy data directory '%s' does not exist" directory ]
        else
            Directory.GetFiles directory
            |> Array.choose (fun path ->
                match importFile connection path with
                | Ok() -> None
                | Error message -> Some message)
            |> Array.toList
