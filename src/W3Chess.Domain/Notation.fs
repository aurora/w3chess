namespace W3Chess.Domain

/// Move notation: UCI, SAN and PGN.
module Notation =
    let pieceLetter kind =
        match kind with
        | King -> 'K'
        | Queen -> 'Q'
        | Rook -> 'R'
        | Bishop -> 'B'
        | Knight -> 'N'
        | Pawn -> 'p'

    /// Long algebraic / UCI notation, e.g. "e2e4" or "e7e8q".
    let toUci (move: Move) =
        Square.toAlgebraic move.From
        + Square.toAlgebraic move.To
        + (match move.Promotion with
           | Some kind -> string (System.Char.ToLower(pieceLetter kind))
           | None -> "")

    /// Parses UCI notation back into a move.
    let ofUci (text: string) : Move option =
        if text.Length < 4 then
            None
        else
            match Square.ofAlgebraic (text.Substring(0, 2)), Square.ofAlgebraic (text.Substring(2, 2)) with
            | Some from, Some target ->
                let promotion =
                    if text.Length >= 5 then
                        match System.Char.ToLower text.[4] with
                        | 'q' -> Some Queen
                        | 'r' -> Some Rook
                        | 'b' -> Some Bishop
                        | 'n' -> Some Knight
                        | _ -> None
                    else
                        None

                Some { From = from; To = target; Promotion = promotion }
            | _ -> None

    let private isCastle (move: Move) (piece: Piece) =
        piece.Kind = King && abs (Square.file move.To - Square.file move.From) = 2

    let private disambiguate (position: Position) (move: Move) (piece: Piece) =
        let sharingTarget =
            MoveGen.legalMoves position
            |> List.filter (fun other ->
                other.From <> move.From
                && other.To = move.To
                && (position.Board.[other.From] |> Option.map (fun p -> p.Kind)) = Some piece.Kind)

        if List.isEmpty sharingTarget then
            ""
        elif sharingTarget |> List.forall (fun other -> Square.file other.From <> Square.file move.From) then
            string (char (int 'a' + Square.file move.From))
        elif sharingTarget |> List.forall (fun other -> Square.rank other.From <> Square.rank move.From) then
            string (char (int '1' + Square.rank move.From))
        else
            Square.toAlgebraic move.From

    let private sanBase (position: Position) (move: Move) =
        let piece = position.Board.[move.From].Value

        if isCastle move piece then
            if Square.file move.To = 6 then "O-O" else "O-O-O"
        else
            let isCapture =
                position.Board.[move.To].IsSome
                || (piece.Kind = Pawn && Square.file move.From <> Square.file move.To)

            let target = Square.toAlgebraic move.To

            let promotion =
                match move.Promotion with
                | Some kind -> "=" + string (pieceLetter kind)
                | None -> ""

            let captureText = if isCapture then "x" else ""

            if piece.Kind = Pawn then
                (if isCapture then string (char (int 'a' + Square.file move.From)) else "")
                + captureText
                + target
                + promotion
            else
                string (pieceLetter piece.Kind)
                + disambiguate position move piece
                + captureText
                + target

    /// Standard algebraic notation for a move in a position.
    let toSan (position: Position) (move: Move) =
        let next = MoveGen.applyMove position move

        let suffix =
            if Rules.inCheck next next.SideToMove then
                if List.isEmpty (MoveGen.legalMoves next) then "#" else "+"
            else
                ""

        sanBase position move + suffix

    /// Builds a `MoveRecord` (move plus derived history metadata).
    let toRecord (position: Position) (move: Move) : MoveRecord =
        let next = MoveGen.applyMove position move
        let piece = position.Board.[move.From].Value
        let captured = position.Board.[move.To]
        let check = Rules.inCheck next next.SideToMove
        let checkmate = check && List.isEmpty (MoveGen.legalMoves next)

        { Move = move
          San = toSan position move
          Piece = piece
          Captured = captured
          IsCheck = check
          IsCheckmate = checkmate }

    /// Renders a move list as PGN move text ("1. e4 e5 2. Nf3 ...").
    let toPgn (position: Position) (moves: MoveRecord list) : string =
        let rec loop pos ply acc remaining =
            match remaining with
            | [] -> List.rev acc
            | record :: rest ->
                let prefix =
                    if ply % 2 = 0 then string (ply / 2 + 1) + ". " else ""

                loop (MoveGen.applyMove pos record.Move) (ply + 1) (prefix + record.San :: acc) rest

        loop position 0 [] moves |> String.concat " "
