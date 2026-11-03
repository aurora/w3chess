namespace W3Chess.Domain

/// Game-state evaluation: check, checkmate, stalemate and the draw conditions
/// that can be derived from a single position.
module Rules =
    type DrawReason =
        | FiftyMoveRule
        | InsufficientMaterial

    type Status =
        | InProgress
        | Check
        | Checkmate
        | Draw of DrawReason
        | Stalemate

    /// Is `color`'s king currently attacked?
    let inCheck (position: Position) (color: Color) =
        match MoveGen.findKing position.Board color with
        | Some kingSquare -> MoveGen.isSquareAttacked position kingSquare (Color.opposite color)
        | None -> false

    /// K vs K, K+B vs K and K+N vs K are dead draws.
    let insufficientMaterial (board: Piece option[]) =
        let minors =
            board
            |> Array.choose id
            |> Array.filter (fun piece -> piece.Kind <> King)

        match minors with
        | [||] -> true
        | [| { Kind = Bishop } |]
        | [| { Kind = Knight } |] -> true
        | _ -> false

    /// Evaluates a position that is assumed to be a real position (both kings present).
    let status (position: Position) : Status =
        let moves = MoveGen.legalMoves position
        let check = inCheck position position.SideToMove

        if List.isEmpty moves then
            if check then Checkmate else Stalemate
        elif position.HalfmoveClock >= 100 then
            Draw FiftyMoveRule
        elif insufficientMaterial position.Board then
            Draw InsufficientMaterial
        elif check then
            Check
        else
            InProgress

    let isGameOver (position: Position) =
        match status position with
        | Checkmate
        | Stalemate
        | Draw _ -> true
        | InProgress
        | Check -> false

    /// Identity of a position for repetition purposes (FIDE 9.2): piece
    /// placement, side to move and castling rights, plus the en passant square
    /// only when an en passant capture is actually legal — a diagonal pawn
    /// move to the square; a pinned pawn cannot capture, so it does not make
    /// the square count.
    let repetitionKey (position: Position) : string =
        let hasEnPassantCapture =
            match position.EnPassant with
            | None -> false
            | Some target ->
                MoveGen.legalMoves position
                |> List.exists (fun move ->
                    move.To = target
                    && Square.file move.From <> Square.file target
                    && (match position.Board.[move.From] with
                        | Some piece -> piece.Kind = Pawn
                        | None -> false))

        let parts = (Board.toFen position).Split(' ')
        let enPassant = if hasEnPassantCapture then parts.[3] else "-"

        sprintf "%s %s %s %s" parts.[0] parts.[1] parts.[2] enPassant

    /// FIDE 9.2: the same position has occurred three times in the game,
    /// counting the current one. Irreversible moves reset the half-move clock
    /// and cannot lie between two equal placements, so scanning the whole
    /// history is safe.
    let isThreefoldRepetition (history: Position list) : bool =
        match List.tryLast history with
        | None -> false
        | Some current ->
            let key = repetitionKey current

            List.sumBy
                (fun position -> if repetitionKey position = key then 1 else 0)
                history >= 3
