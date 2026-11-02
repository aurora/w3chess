namespace W3Chess.Domain

/// Colour of a piece and of the side to move.
type Color =
    | White
    | Black

module Color =
    let opposite color =
        match color with
        | White -> Black
        | Black -> White

    let toString color =
        match color with
        | White -> "white"
        | Black -> "black"

/// Kind of a chess piece, independent of colour.
type PieceKind =
    | Pawn
    | Knight
    | Bishop
    | Rook
    | Queen
    | King

/// A concrete piece on the board.
type Piece =
    { Color: Color
      Kind: PieceKind }

/// A square encoded as a 0-based index: `rank * 8 + file`, where
/// `rank = 0` is rank 1 and `file = 0` is file 'a'. Range 0 (a1) .. 63 (h8).
type Square = int

module Square =
    let create file rank = rank * 8 + file
    let file (square: Square) = square % 8
    let rank (square: Square) = square / 8
    let isValid (square: Square) = square >= 0 && square < 64
    let all: Square list = [ 0..63 ]

    let toAlgebraic (square: Square) =
        string (char (int 'a' + file square)) + string (char (int '1' + rank square))

    let ofAlgebraic (text: string) =
        if text.Length <> 2 then
            None
        else
            let f = int text.[0] - int 'a'
            let r = int text.[1] - int '1'
            if f >= 0 && f < 8 && r >= 0 && r < 8 then Some(create f r) else None

/// Which castling rights still exist.
type CastlingRights =
    { WhiteKingSide: bool
      WhiteQueenSide: bool
      BlackKingSide: bool
      BlackQueenSide: bool }

module CastlingRights =
    let none =
        { WhiteKingSide = false
          WhiteQueenSide = false
          BlackKingSide = false
          BlackQueenSide = false }

    let all =
        { WhiteKingSide = true
          WhiteQueenSide = true
          BlackKingSide = true
          BlackQueenSide = true }

/// A full, immutable chess position.
type Position =
    { Board: Piece option[]
      SideToMove: Color
      Castling: CastlingRights
      EnPassant: Square option
      HalfmoveClock: int
      FullmoveNumber: int }

/// A move as played. `Promotion` is only set for pawn promotion moves.
type Move =
    { From: Square
      To: Square
      Promotion: PieceKind option }

/// A move together with the metadata needed for a move history / PGN.
type MoveRecord =
    { Move: Move
      San: string
      Piece: Piece
      Captured: Piece option
      IsCheck: bool
      IsCheckmate: bool }
