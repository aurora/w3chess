namespace W3Chess.Domain

/// Metadata of one player as stored in a legacy text file.
type LegacyPlayer =
    { Nickname: string
      Email: string
      HasOwnPassword: bool }

/// Outcome encoded in a legacy file.
type LegacyResult =
    | StillPlaying
    | WhiteWins
    | BlackWins
    | Drawn

/// A fully parsed legacy game file.
type LegacyGame =
    { Id: string
      IsOpen: bool
      White: LegacyPlayer
      Black: LegacyPlayer
      Result: LegacyResult
      ResultMarker: string option
      MoveRecords: MoveRecord list
      FinalPosition: Position
      StoredBoard: string
      BoardMatchesReplay: bool
      SideToMove: Color
      Message: string option
      DrawOfferPending: bool
      MoveToken: string }

type LegacyImportError = { GameId: string; Message: string }

/// Parser for the legacy w3chess game file format.
///
/// File layout (one game per file):
///   1: <nick1>[:<pass>][:1:]      (nick1 is the player *to move*)
///   2: <mail1>
///   3: <nick2>[:<pass>][:1:]
///   4: <mail2>
///   5: <moveToken | "[MATE]" | "[REMIS]" | "[GIVEUP]">
///   6: <moveCount>
///   7: <moves>                    (moveCount * 7 characters, empty when 0)
///   8: <board>                    (64 characters, rank 8 first, 'e' = empty)
///   9: [@<message>]               (optional)
///
/// Each move record is 7 characters: piece, fromFile, fromRank, toFile, toRank,
/// resulting piece ('O' marks en passant), captured piece ('e' if none).
module LegacyImport =
    let private toLower (c: char) = if c >= 'A' && c <= 'Z' then char (int c + 32) else c

    let private splitLines (text: string) =
        text.Replace("\r\n", "\n").Replace("\r", "\n").Split('\n') |> Array.toList

    /// Parses a nickname line into the player and the player's own password.
    let parseNicknameLine (line: string) =
        let lastColon = line.LastIndexOf(':')

        if lastColon < 0 then
            ({ Nickname = line; Email = ""; HasOwnPassword = false }, "")
        else
            let nickname = line.Substring(lastColon + 1)
            let before = line.Substring(0, lastColon)
            let password = (before.Split(':')).[0]

            ({ Nickname = nickname
               Email = ""
               HasOwnPassword = password <> "" },
             password)

    let private parseMoveRecord (position: Position) (record: string) : Move option =
        if record.Length < 7 then
            None
        else
            let fromSquare = Square.create (int (toLower record.[1]) - int 'a') (int record.[2] - int '1')
            let toSquare = Square.create (int (toLower record.[3]) - int 'a') (int record.[4] - int '1')
            let toRank = int record.[4] - int '1'

            let promotion =
                match position.Board.[fromSquare] with
                | Some { Kind = Pawn } when toRank = 7 || toRank = 0 ->
                    match System.Char.ToUpper record.[5] with
                    | 'R' -> Some Rook
                    | 'B' -> Some Bishop
                    | 'N' -> Some Knight
                    | _ -> Some Queen
                | _ -> None

            Some { From = fromSquare; To = toSquare; Promotion = promotion }

    let private stripMessagePrefix (message: string) =
        // leading '@' is the message marker; "[REMIS?]@" marks a pending draw offer
        let withoutMarker =
            if message.StartsWith("[REMIS?]@") then message.Substring(9)
            elif message.StartsWith("@") then message.Substring(1)
            else message

        withoutMarker

    /// Parses the content of a legacy game file.
    let parseContent (id: string) (isOpen: bool) (content: string) : Result<LegacyGame, LegacyImportError> =
        let line index =
            let lines = splitLines content
            if index < lines.Length then lines.[index] else ""

        let nickLine1 = line 0
        let mail1 = (line 1).Trim()
        let nickLine2 = line 2
        let mail2 = (line 3).Trim()
        let token = (line 4).Trim()
        let moveCountText = (line 5).Trim()
        let movesText = line 6
        let boardText = (line 7).Trim()
        let messageLine = line 8

        match System.Int32.TryParse moveCountText with
        | false, _ ->
            Error { GameId = id; Message = "missing or invalid move count" }
        | true, moveCount ->
            if boardText.Length < 64 then
                Error { GameId = id; Message = "board line shorter than 64 characters" }
            else
                let player1, _ = parseNicknameLine nickLine1
                let player2, _ = parseNicknameLine nickLine2
                let sideToMove = if moveCount % 2 = 0 then Color.White else Color.Black

                let white, black =
                    if sideToMove = Color.White then
                        ({ player1 with Email = mail1 }, { player2 with Email = mail2 })
                    else
                        ({ player2 with Email = mail2 }, { player1 with Email = mail1 })

                let records =
                    if moveCount <= 0 then
                        []
                    else
                        [ for ply in 0 .. moveCount - 1 do
                              let start = ply * 7

                              if start + 7 <= movesText.Length then
                                  yield movesText.Substring(start, 7) ]

                let finalPosition, moveRecords, allLegal =
                    records
                    |> List.fold
                        (fun (position, acc, legal) record ->
                            match parseMoveRecord position record with
                            | Some move ->
                                let isLegal = MoveGen.isLegalMove position move

                                let position' =
                                    MoveGen.applyMove position move

                                (position', Notation.toRecord position move :: acc, legal && isLegal)
                            | None -> (position, acc, false))
                        (Board.startPosition, [], true)

                let replayBoard = Board.toLegacyBoard finalPosition.Board
                let storedBoard = boardText.Substring(0, 64)

                let result, marker =
                    match token with
                    | "[MATE]" -> (if sideToMove = Color.White then BlackWins else WhiteWins), Some token
                    | "[GIVEUP]" -> (if sideToMove = Color.White then BlackWins else WhiteWins), Some token
                    | "[REMIS]" -> Drawn, Some token
                    | _ -> StillPlaying, None

                let hasMessage = messageLine.StartsWith("@") || messageLine.StartsWith("[REMIS?]@")

                Ok
                    { Id = id
                      IsOpen = isOpen
                      White = white
                      Black = black
                      Result = result
                      ResultMarker = marker
                      MoveRecords = List.rev moveRecords
                      FinalPosition = finalPosition
                      StoredBoard = storedBoard
                      BoardMatchesReplay = replayBoard = storedBoard && allLegal
                      SideToMove = sideToMove
                      Message = if hasMessage then Some(stripMessagePrefix messageLine) else None
                      DrawOfferPending = messageLine.StartsWith("[REMIS?]@")
                      MoveToken = if marker.IsNone then token else "" }
