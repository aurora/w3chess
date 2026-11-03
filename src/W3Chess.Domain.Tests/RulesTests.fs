module W3Chess.Domain.Tests.RulesTests

open Xunit
open W3Chess.Domain

let private applyMoves (moves: string list) =
    moves
    |> List.fold
        (fun position uci ->
            let move = Notation.ofUci uci |> Option.get
            Assert.True(MoveGen.isLegalMove position move, $"Expected {uci} to be legal in {Board.toFen position}")
            MoveGen.applyMove position move)
        Board.startPosition

[<Fact>]
let ``initial position has twenty legal moves`` () =
    Assert.Equal(20, MoveGen.legalMoves Board.startPosition |> List.length)

[<Fact>]
let ``scholars mate is checkmate`` () =
    let final = applyMoves [ "e2e4"; "e7e5"; "f1c4"; "b8c6"; "d1h5"; "g8f6"; "h5f7" ]

    Assert.Equal(Rules.Checkmate, Rules.status final)
    Assert.True(Rules.inCheck final Color.Black)

[<Fact>]
let ``both castlings are available when the path is clear`` () =
    let position = Board.ofFen "r3k2r/8/8/8/8/8/8/R3K2R w KQkq - 0 1"

    let moves = MoveGen.legalMoves position

    let hasMove fromSquare toSquare =
        moves |> List.exists (fun move -> move.From = fromSquare && move.To = toSquare)

    Assert.True(hasMove 4 6, "expected king-side castling")
    Assert.True(hasMove 4 2, "expected queen-side castling")

[<Fact>]
let ``castling is not allowed through an attacked square`` () =
    let position = Board.ofFen "r3k2r/8/8/8/8/8/6q1/R3K2R w KQkq - 0 1"

    let moves = MoveGen.legalMoves position

    let hasMove fromSquare toSquare =
        moves |> List.exists (fun move -> move.From = fromSquare && move.To = toSquare)

    Assert.True(hasMove 4 2, "queen-side castling should still be legal")
    Assert.False(hasMove 4 6, "king-side castling crosses g1 which is attacked")

[<Fact>]
let ``en passant capture is generated`` () =
    let position =
        Board.ofFen "rnbqkbnr/ppppp1pp/8/4Pp2/8/8/PPPP1PPP/RNBQKBNR w KQkq f6 0 3"

    let move =
        MoveGen.legalMoves position
        |> List.find (fun m -> m.From = Square.create 4 4 && m.To = Square.create 5 5)

    let after = MoveGen.applyMove position move

    Assert.True(after.Board.[Square.create 5 4].IsNone, "the captured pawn on f5 must be removed")
    Assert.True(after.Board.[Square.create 5 5].IsSome, "the white pawn must stand on f6")

[<Fact>]
let ``en passant capture records the captured pawn`` () =
    let position =
        Board.ofFen "rnbqkbnr/ppppp1pp/8/4Pp2/8/8/PPPP1PPP/RNBQKBNR w KQkq f6 0 3"

    let move =
        MoveGen.legalMoves position
        |> List.find (fun m -> m.From = Square.create 4 4 && m.To = Square.create 5 5)

    let record = Notation.toRecord position move

    Assert.Equal(Some { Color = Color.Black; Kind = Pawn }, record.Captured)
    Assert.Equal("exf6", record.San)

[<Fact>]
let ``pawn promotion offers four pieces`` () =
    let position = Board.ofFen "8/P7/8/8/8/8/8/K6k w - - 0 1"

    let promotions =
        MoveGen.legalMoves position
        |> List.filter (fun move -> move.From = Square.create 0 6 && move.To = Square.create 0 7)

    Assert.Equal(4, List.length promotions)
    Assert.Equal<PieceKind list>([ Queen; Rook; Bishop; Knight ], promotions |> List.map (fun m -> m.Promotion.Value))

[<Fact>]
let ``stalemate is detected`` () =
    let position = Board.ofFen "7k/5Q2/6K1/8/8/8/8/8 b - - 0 1"

    Assert.False(Rules.inCheck position Color.Black)
    Assert.Equal(Rules.Stalemate, Rules.status position)

[<Fact>]
let ``san uses castling, capture and check markers`` () =
    let position = Board.ofFen "r3k2r/8/8/8/8/8/8/R3K2R w KQkq - 0 1"

    let kingSideCastle =
        MoveGen.legalMoves position
        |> List.find (fun move -> move.From = 4 && move.To = 6)

    Assert.Equal("O-O", Notation.toSan position kingSideCastle)

[<Fact>]
let ``fen round trips`` () =
    let fens =
        [ Board.startFen
          "r3k2r/p1ppqpb1/bn2pnp1/3PN3/1p2P3/2N2Q1p/PPPBBPPP/R3K2R w KQkq - 0 1"
          "8/2p5/3p4/KP5r/1R3p1k/8/4P1P1/8 w - - 0 1" ]

    for fen in fens do
        Assert.Equal(fen, Board.toFen (Board.ofFen fen))
