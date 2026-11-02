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
