module W3Chess.Domain.Tests.LegacyImportTests

open Xunit
open W3Chess.Domain

let private content lines = String.concat "\n" lines

let private startBoard =
    String.concat
        ""
        [ "rnbqkbnr"; "pppppppp"; "eeeeeeee"; "eeeeeeee"
          "eeeeeeee"; "eeeeeeee"; "PPPPPPPP"; "RNBQKBNR" ]

[<Fact>]
let ``nickname line without password`` () =
    let player, password = LegacyImport.parseNicknameLine "Alice"
    Assert.Equal("Alice", player.Nickname)
    Assert.False(player.HasOwnPassword)
    Assert.Equal("", password)

[<Fact>]
let ``nickname line with password and notify flag`` () =
    let player, password = LegacyImport.parseNicknameLine "secret:1:Bob"
    Assert.Equal("Bob", player.Nickname)
    Assert.True(player.HasOwnPassword)
    Assert.Equal("secret", password)

[<Fact>]
let ``parses an open invitation`` () =
    let file =
        content
            [ "Creator"
              "creator@example.com"
              ""
              ""
              "PENDINGTOKEN"
              "0"
              ""
              startBoard ]

    match LegacyImport.parseContent "X20260101-1" true file with
    | Error error -> failwith error.Message
    | Ok game ->
        Assert.True(game.IsOpen)
        Assert.Equal("Creator", game.White.Nickname)
        Assert.Equal("creator@example.com", game.White.Email)
        Assert.Equal("", game.Black.Nickname)
        Assert.Empty(game.MoveRecords)
        Assert.Equal(Board.startFen, Board.toFen game.FinalPosition)
        Assert.Equal(startBoard, game.StoredBoard)
        Assert.True(game.BoardMatchesReplay)
        Assert.Equal(LegacyResult.StillPlaying, game.Result)
        Assert.Equal("PENDINGTOKEN", game.MoveToken)

[<Fact>]
let ``parses a single move and assigns colours by move parity`` () =
    let boardAfterE4 =
        String.concat
            ""
            [ "rnbqkbnr"; "pppppppp"; "eeeeeeee"; "eeeeeeee"
              "eeeePeee"; "eeeeeeee"; "PPPPePPP"; "RNBQKBNR" ]

    let file =
        content
            [ "pw:1:Bob"
              "bob@example.com"
              "Alice"
              "alice@example.com"
              "TOKEN123"
              "1"
              "PE2E4Pe"
              boardAfterE4 ]

    match LegacyImport.parseContent "20260101-1" false file with
    | Error error -> failwith error.Message
    | Ok game ->
        Assert.Equal("Alice", game.White.Nickname)
        Assert.Equal("alice@example.com", game.White.Email)
        Assert.Equal("Bob", game.Black.Nickname)
        Assert.True(game.Black.HasOwnPassword)
        Assert.Equal(Color.Black, game.SideToMove)
        Assert.Equal<string list>([ "e4" ], game.MoveRecords |> List.map (fun r -> r.San))
        Assert.Equal(boardAfterE4, game.StoredBoard)
        Assert.True(game.BoardMatchesReplay)
        Assert.Equal(LegacyResult.StillPlaying, game.Result)

[<Fact>]
let ``detects checkmate and the winner`` () =
    let moves =
        String.concat
            ""
            [ "PE2E4Pe"
              "pE7E5pe"
              "BF1C4Be"
              "nB8C6ne"
              "QD1H5Qe"
              "nG8F6ne"
              "QH5F7Qp" ]

    let boardAfterMate =
        String.concat
            ""
            [ "rebqkber"; "ppppeQpp"; "eeneenee"; "eeeepeee"
              "eeBePeee"; "eeeeeeee"; "PPPPePPP"; "RNBeKeNR" ]

    let file =
        content
            [ "Bob"
              "bob@example.com"
              "Alice"
              "alice@example.com"
              "[MATE]"
              "7"
              moves
              boardAfterMate ]

    match LegacyImport.parseContent "20260101-1" false file with
    | Error error -> failwith error.Message
    | Ok game ->
        Assert.Equal(LegacyResult.WhiteWins, game.Result)
        Assert.Equal(Some "[MATE]", game.ResultMarker)
        Assert.Equal(7, List.length game.MoveRecords)
        Assert.Equal("Qxf7#", (List.last game.MoveRecords).San)
        Assert.True(game.BoardMatchesReplay)
        Assert.Equal(Rules.Checkmate, Rules.status game.FinalPosition)

[<Fact>]
let ``detects a pending draw offer in the message`` () =
    let file =
        content
            [ "Creator"
              "creator@example.com"
              ""
              ""
              "TOKEN"
              "0"
              ""
              startBoard
              "[REMIS?]@Peace?" ]

    match LegacyImport.parseContent "X20260101-1" true file with
    | Error error -> failwith error.Message
    | Ok game ->
        Assert.True(game.DrawOfferPending)
        Assert.Equal(Some "Peace?", game.Message)
