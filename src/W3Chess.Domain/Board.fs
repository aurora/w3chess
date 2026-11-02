namespace W3Chess.Domain

module Board =
    let empty: Piece option[] = Array.create 64 None

    let tryPiece (board: Piece option[]) (square: Square) =
        if Square.isValid square then board.[square] else None

    let private isUpper (c: char) = c >= 'A' && c <= 'Z'

    let private toLower (c: char) =
        if c >= 'A' && c <= 'Z' then char (int c + 32) else c

    let private pieceFromChar (c: char) =
        let color = if isUpper c then Color.White else Color.Black

        match toLower c with
        | 'p' -> Some { Color = color; Kind = Pawn }
        | 'n' -> Some { Color = color; Kind = Knight }
        | 'b' -> Some { Color = color; Kind = Bishop }
        | 'r' -> Some { Color = color; Kind = Rook }
        | 'q' -> Some { Color = color; Kind = Queen }
        | 'k' -> Some { Color = color; Kind = King }
        | _ -> None

    let private charFromPiece (piece: Piece) =
        let c =
            match piece.Kind with
            | Pawn -> 'p'
            | Knight -> 'n'
            | Bishop -> 'b'
            | Rook -> 'r'
            | Queen -> 'q'
            | King -> 'k'

        if piece.Color = Color.White then char (int c - 32) else c

    /// Parses a FEN string into a position. Throws on malformed input.
    let ofFen (fen: string) : Position =
        let parts = fen.Trim().Split(' ')
        let placement = parts.[0]
        let board = Array.create 64 None

        placement.Split('/')
        |> Array.iteri (fun index rankText ->
            let rankIndex = 7 - index
            let mutable fileIndex = 0

            for c in rankText do
                if c >= '1' && c <= '8' then
                    fileIndex <- fileIndex + (int c - int '0')
                else
                    match pieceFromChar c with
                    | Some piece ->
                        board.[Square.create fileIndex rankIndex] <- Some piece
                        fileIndex <- fileIndex + 1
                    | None -> fileIndex <- fileIndex + 1)

        let sideToMove =
            if parts.Length > 1 && parts.[1] = "b" then Color.Black else Color.White

        let castling =
            if parts.Length > 2 && parts.[2] <> "-" then
                let rights = parts.[2]

                { WhiteKingSide = rights.Contains "K"
                  WhiteQueenSide = rights.Contains "Q"
                  BlackKingSide = rights.Contains "k"
                  BlackQueenSide = rights.Contains "q" }
            else
                CastlingRights.none

        let enPassant =
            if parts.Length > 3 && parts.[3] <> "-" then Square.ofAlgebraic parts.[3]
            else None

        let parseInt index fallback =
            if parts.Length > index then
                match System.Int32.TryParse parts.[index] with
                | true, value -> value
                | _ -> fallback
            else
                fallback

        { Board = board
          SideToMove = sideToMove
          Castling = castling
          EnPassant = enPassant
          HalfmoveClock = parseInt 4 0
          FullmoveNumber = parseInt 5 1 }

    /// Formats a position as a FEN string.
    let toFen (position: Position) : string =
        let ranks = [ 7..-1..0 ]

        let rankText rank =
            let mutable text = ""
            let mutable emptyRun = 0

            for file in 0..7 do
                match position.Board.[Square.create file rank] with
                | Some piece ->
                    if emptyRun > 0 then
                        text <- text + string emptyRun
                        emptyRun <- 0

                    text <- text + string (charFromPiece piece)
                | None -> emptyRun <- emptyRun + 1

            if emptyRun > 0 then text <- text + string emptyRun
            text

        let placement = ranks |> List.map rankText |> String.concat "/"

        let castling =
            let c = position.Castling
            let text =
                (if c.WhiteKingSide then "K" else "")
                + (if c.WhiteQueenSide then "Q" else "")
                + (if c.BlackKingSide then "k" else "")
                + (if c.BlackQueenSide then "q" else "")

            if text = "" then "-" else text

        let enPassant =
            match position.EnPassant with
            | Some square -> Square.toAlgebraic square
            | None -> "-"

        let sideToMove = if position.SideToMove = Color.White then "w" else "b"

        String.concat
            " "
            [ placement
              sideToMove
              castling
              enPassant
              string position.HalfmoveClock
              string position.FullmoveNumber ]

    let startFen = "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1"

    let startPosition = ofFen startFen

    /// Reads a legacy board string (64 characters, rank 8 first) into a board.
    let ofLegacyBoard (text: string) : Piece option[] =
        let board = Array.create 64 None

        for index in 0 .. min 63 (text.Length - 1) do
            match pieceFromChar text.[index] with
            | Some piece ->
                let file = index % 8
                let rank = 7 - (index / 8)
                board.[Square.create file rank] <- Some piece
            | None -> ()

        board

    /// Serialises a board in the legacy 64-character form (rank 8 first).
    let toLegacyBoard (board: Piece option[]) : string =
        [ for rank in 7..-1..0 do
              for file in 0..7 do
                  match board.[Square.create file rank] with
                  | Some piece -> charFromPiece piece
                  | None -> 'e' ]
        |> List.toArray
        |> System.String
