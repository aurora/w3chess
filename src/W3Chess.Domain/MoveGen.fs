namespace W3Chess.Domain

/// Pseudo-legal and legal move generation. The logic mirrors the rules of the
/// legacy C engine (`canmove`, `kingIsAttacked`, `checkMove`, `wayFree`) but is
/// implemented on an immutable `Position`.
module MoveGen =
    let private knightOffsets =
        [ (1, 2); (2, 1); (2, -1); (1, -2); (-1, -2); (-2, -1); (-2, 1); (-1, 2) ]

    let private kingOffsets =
        [ (1, 0); (1, 1); (0, 1); (-1, 1); (-1, 0); (-1, -1); (0, -1); (1, -1) ]

    let private bishopDirs = [ (1, 1); (1, -1); (-1, 1); (-1, -1) ]
    let private rookDirs = [ (1, 0); (-1, 0); (0, 1); (0, -1) ]

    let private squareAt file rank =
        if file >= 0 && file < 8 && rank >= 0 && rank < 8 then
            Some(Square.create file rank)
        else
            None

    let private opposite = Color.opposite

    /// Is `target` attacked by any piece of `byColor`?
    let isSquareAttacked (position: Position) (target: Square) (byColor: Color) =
        let board = position.Board
        let tf = Square.file target
        let tr = Square.rank target

        let hasPiece square kind =
            match square with
            | Some sq ->
                match board.[sq] with
                | Some piece -> piece.Color = byColor && piece.Kind = kind
                | None -> false
            | None -> false

        let pawnRank = if byColor = Color.White then tr - 1 else tr + 1

        let pawnAttacks =
            hasPiece (squareAt (tf - 1) pawnRank) Pawn
            || hasPiece (squareAt (tf + 1) pawnRank) Pawn

        let knightAttacks =
            knightOffsets
            |> List.exists (fun (df, dr) -> hasPiece (squareAt (tf + df) (tr + dr)) Knight)

        let kingAttacks =
            kingOffsets
            |> List.exists (fun (df, dr) -> hasPiece (squareAt (tf + df) (tr + dr)) King)

        let rayAttacks (dirs: (int * int) list) kinds =
            [ for (df, dr) in dirs do
                  let mutable f = tf + df
                  let mutable r = tr + dr
                  let mutable stop = false

                  while not stop && f >= 0 && f < 8 && r >= 0 && r < 8 do
                      let sq = Square.create f r

                      match board.[sq] with
                      | Some piece ->
                          if piece.Color = byColor && List.contains piece.Kind kinds then
                              yield true

                          stop <- true
                      | None -> ()

                      f <- f + df
                      r <- r + dr ]
            |> List.contains true

        pawnAttacks
        || knightAttacks
        || kingAttacks
        || rayAttacks bishopDirs [ Bishop; Queen ]
        || rayAttacks rookDirs [ Rook; Queen ]

    let findKing (board: Piece option[]) (color: Color) : Square option =
        Array.tryFindIndex (fun piece -> piece = Some { Color = color; Kind = King }) board

    let private jumpMoves (position: Position) (from: Square) offsets =
        let color = position.SideToMove

        [ for (df, dr) in offsets do
              match squareAt (Square.file from + df) (Square.rank from + dr) with
              | Some target ->
                  match position.Board.[target] with
                  | Some piece when piece.Color = color -> ()
                  | _ -> yield { From = from; To = target; Promotion = None }
              | None -> () ]

    let private rayMoves (board: Piece option[]) (from: Square) color dirs =
        [ for (df, dr) in dirs do
              let mutable f = Square.file from + df
              let mutable r = Square.rank from + dr
              let mutable stop = false

              while not stop && f >= 0 && f < 8 && r >= 0 && r < 8 do
                  let target = Square.create f r

                  match board.[target] with
                  | Some piece when piece.Color = color -> stop <- true
                  | Some _ ->
                      yield { From = from; To = target; Promotion = None }
                      stop <- true
                  | None -> yield { From = from; To = target; Promotion = None }

                  f <- f + df
                  r <- r + dr ]

    let private pawnMoves (position: Position) (from: Square) =
        let board = position.Board
        let color = position.SideToMove
        let direction = if color = Color.White then 1 else -1
        let startRank = if color = Color.White then 1 else 6
        let promotionRank = if color = Color.White then 7 else 0

        let withPromotion target =
            if Square.rank target = promotionRank then
                [ Queen; Rook; Bishop; Knight ]
                |> List.map (fun kind -> { From = from; To = target; Promotion = Some kind })
            else
                [ { From = from; To = target; Promotion = None } ]

        [ // single push
          match squareAt (Square.file from) (Square.rank from + direction) with
          | Some target when board.[target].IsNone -> yield! withPromotion target
          | _ -> ()

          // double push from the start rank
          if Square.rank from = startRank then
              match squareAt (Square.file from) (Square.rank from + (2 * direction)) with
              | Some target when
                  board.[target].IsNone
                  && board.[Square.create (Square.file from) (Square.rank from + direction)].IsNone
                  ->
                  yield { From = from; To = target; Promotion = None }
              | _ -> ()

          // diagonal captures and en passant
          for df in [ -1; 1 ] do
              match squareAt (Square.file from + df) (Square.rank from + direction) with
              | Some target ->
                  match board.[target] with
                  | Some piece when piece.Color <> color -> yield! withPromotion target
                  | Some _ -> ()
                  | None ->
                      if position.EnPassant = Some target then
                          yield { From = from; To = target; Promotion = None }
              | None -> () ]

    let private castlingMoves (position: Position) (from: Square) =
        let color = position.SideToMove
        let board = position.Board
        let homeRank = if color = Color.White then 0 else 7

        if from <> Square.create 4 homeRank then
            []
        else
            let enemy = opposite color
            let kingSideRight = if color = Color.White then position.Castling.WhiteKingSide else position.Castling.BlackKingSide
            let queenSideRight = if color = Color.White then position.Castling.WhiteQueenSide else position.Castling.BlackQueenSide

            let squareIsSafe file =
                not (isSquareAttacked position (Square.create file homeRank) enemy)

            let isEmpty file = board.[Square.create file homeRank].IsNone

            [ if
                  kingSideRight
                  && isEmpty 5
                  && isEmpty 6
                  && squareIsSafe 4
                  && squareIsSafe 5
                  && squareIsSafe 6
              then
                  yield { From = from; To = Square.create 6 homeRank; Promotion = None }

              if
                  queenSideRight
                  && isEmpty 1
                  && isEmpty 2
                  && isEmpty 3
                  && squareIsSafe 4
                  && squareIsSafe 3
                  && squareIsSafe 2
              then
                  yield { From = from; To = Square.create 2 homeRank; Promotion = None } ]

    /// All pseudo-legal moves for the side to move (king safety not checked).
    let pseudoMoves (position: Position) : Move list =
        let color = position.SideToMove

        [ for square in Square.all do
              match position.Board.[square] with
              | Some piece when piece.Color = color ->
                  match piece.Kind with
                  | Pawn -> yield! pawnMoves position square
                  | Knight -> yield! jumpMoves position square knightOffsets
                  | Bishop -> yield! rayMoves position.Board square color bishopDirs
                  | Rook -> yield! rayMoves position.Board square color rookDirs
                  | Queen -> yield! rayMoves position.Board square color (bishopDirs @ rookDirs)
                  | King ->
                      yield! jumpMoves position square kingOffsets
                      yield! castlingMoves position square
              | _ -> () ]

    /// Applies a move without validating it and returns the resulting position.
    let applyMove (position: Position) (move: Move) : Position =
        let board = Array.copy position.Board
        let piece = board.[move.From].Value
        let color = piece.Color

        let isEnPassant =
            piece.Kind = Pawn
            && Square.file move.From <> Square.file move.To
            && board.[move.To].IsNone

        let isCapture = board.[move.To].IsSome || isEnPassant

        board.[move.From] <- None

        if isEnPassant then
            board.[Square.create (Square.file move.To) (Square.rank move.From)] <- None

        board.[move.To] <-
            match move.Promotion with
            | Some kind -> Some { Color = color; Kind = kind }
            | None -> Some piece

        if piece.Kind = King && abs (Square.file move.To - Square.file move.From) = 2 then
            let rank = Square.rank move.From

            if Square.file move.To = 6 then
                board.[Square.create 7 rank] <- None
                board.[Square.create 5 rank] <- Some { Color = color; Kind = Rook }
            else
                board.[Square.create 0 rank] <- None
                board.[Square.create 3 rank] <- Some { Color = color; Kind = Rook }

        let castling =
            let c = position.Castling
            let onWhite = color = Color.White
            let kingMoved = piece.Kind = King
            let touched square = move.From = square || move.To = square
            let keep current affected = current && not affected

            { WhiteKingSide = keep c.WhiteKingSide ((kingMoved && onWhite) || touched (Square.create 7 0))
              WhiteQueenSide = keep c.WhiteQueenSide ((kingMoved && onWhite) || touched (Square.create 0 0))
              BlackKingSide = keep c.BlackKingSide ((kingMoved && not onWhite) || touched (Square.create 7 7))
              BlackQueenSide = keep c.BlackQueenSide ((kingMoved && not onWhite) || touched (Square.create 0 7)) }

        let enPassant =
            if piece.Kind = Pawn && abs (Square.rank move.To - Square.rank move.From) = 2 then
                Some(Square.create (Square.file move.From) ((Square.rank move.From + Square.rank move.To) / 2))
            else
                None

        let halfmove =
            if piece.Kind = Pawn || isCapture then 0 else position.HalfmoveClock + 1

        let fullmove =
            if color = Color.Black then position.FullmoveNumber + 1 else position.FullmoveNumber

        { Board = board
          SideToMove = opposite color
          Castling = castling
          EnPassant = enPassant
          HalfmoveClock = halfmove
          FullmoveNumber = fullmove }

    /// All fully legal moves for the side to move.
    let legalMoves (position: Position) : Move list =
        let side = position.SideToMove

        pseudoMoves position
        |> List.filter (fun move ->
            let next = applyMove position move

            match findKing next.Board side with
            | Some kingSquare -> not (isSquareAttacked next kingSquare (opposite side))
            | None -> false)

    /// True when `move` is legal in `position`.
    let isLegalMove (position: Position) (move: Move) : bool =
        legalMoves position |> List.exists (fun m -> m = move)
