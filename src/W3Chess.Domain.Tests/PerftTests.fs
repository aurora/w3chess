module W3Chess.Domain.Tests.PerftTests

open Xunit
open W3Chess.Domain

let private perft (position: Position) depth =
    let rec loop pos d =
        if d = 0 then 1L
        else
            MoveGen.legalMoves pos
            |> List.sumBy (fun move -> loop (MoveGen.applyMove pos move) (d - 1))

    loop position depth

[<Fact>]
let ``perft from the initial position`` () =
    let start = Board.startPosition

    Assert.Equal(1L, perft start 0)
    Assert.Equal(20L, perft start 1)
    Assert.Equal(400L, perft start 2)
    Assert.Equal(8902L, perft start 3)
    Assert.Equal(197281L, perft start 4)

[<Fact>]
let ``perft Kiwipete`` () =
    let position =
        Board.ofFen "r3k2r/p1ppqpb1/bn2pnp1/3PN3/1p2P3/2N2Q1p/PPPBBPPP/R3K2R w KQkq - 0 1"

    Assert.Equal(48L, perft position 1)
    Assert.Equal(2039L, perft position 2)
    Assert.Equal(97862L, perft position 3)

[<Fact>]
let ``perft position with en passant and promotion`` () =
    let position = Board.ofFen "8/2p5/3p4/KP5r/1R3p1k/8/4P1P1/8 w - - 0 1"

    Assert.Equal(14L, perft position 1)
    Assert.Equal(191L, perft position 2)
    Assert.Equal(2812L, perft position 3)
