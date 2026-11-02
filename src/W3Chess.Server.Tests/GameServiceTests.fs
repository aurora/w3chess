module W3Chess.Server.Tests.GameServiceTests

open System
open System.IO
open System.Threading.Tasks
open Xunit
open Dapper
open W3Chess.Domain
open W3Chess.Contracts
open W3Chess.Server
open W3Chess.Server.Services
open W3Chess.Server.Storage

let private run (work: Async<unit>) : Task = Async.StartAsTask work :> Task

let private newSettings () =
    let path = Path.Combine(Path.GetTempPath(), sprintf "w3chess-test-%s.db" (Guid.NewGuid().ToString("n")))

    { DatabasePath = path
      LegacyDataDirectory = path + ".games"
      PublicBaseUrl = "http://localhost"
      TokenSecret = "test-secret"
      GameRetentionDays = 0
      RequireAccount = false
      Smtp =
        { Host = "localhost"
          Port = 1025
          UseStartTls = false
          Username = None
          Password = None
          From = "w3chess@localhost"
          SubjectPrefix = "[W3Chess]"
          Enabled = false } }

let private migrate (settings: AppSettings) =
    use connection = Db.connect settings.DatabasePath
    Schema.migrate connection

let private makeService (settings: AppSettings) (mails: ResizeArray<Mail>) =
    let notifier =
        { Send = fun mail -> async { mails.Add mail } }

    GameService(settings, notifier, GameEvents())

let private createOpenRequest =
    { Nickname = "Alice"
      Email = "alice@example.com"
      ColorChoice = ColorChoice.AsWhite
      OpponentNickname = None
      OpponentEmail = None
      Message = None
      Language = "en"; Session = None }

let private unwrap = function
    | Ok value -> value
    | Error message -> failwith message

[<Fact>]
let ``create an open game, join, and exchange moves`` () =
    run (
        async {
            let settings = newSettings ()
            migrate settings
            let mails = ResizeArray<Mail>()
            let service = makeService settings mails

            let alice =
                service.CreateGame createOpenRequest
                |> Async.RunSynchronously
                |> unwrap

            Assert.Equal(Color.White, alice.Color)

            let! waiting = service.GetGame alice.GameId
            Assert.Equal(GameStatus.Waiting, waiting.Value.Status)

            let bob =
                service.JoinGame
                    { GameId = alice.GameId
                      Nickname = "Bob"
                      Email = "bob@example.com"
                      Message = None
                      Language = "en"; Session = None }
                |> Async.RunSynchronously
                |> unwrap

            Assert.Equal(Color.Black, bob.Color)

            let! afterJoin = service.GetGame alice.GameId
            Assert.Equal(GameStatus.Active, afterJoin.Value.Status)
            Assert.Equal(Some "Bob", afterJoin.Value.Black |> Option.map (fun p -> p.Nickname))

            let! whiteMove =
                service.SubmitMove
                    { GameId = alice.GameId
                      PlayerId = alice.PlayerId
                      Token = alice.Token
                      FromSquare = Square.create 4 1
                      ToSquare = Square.create 4 3
                      Promotion = None
                      Message = None; Session = None }

            Assert.True(whiteMove.Ok, sprintf "%A" whiteMove.Error)

            let! blackMove =
                service.SubmitMove
                    { GameId = bob.GameId
                      PlayerId = bob.PlayerId
                      Token = bob.Token
                      FromSquare = Square.create 4 6
                      ToSquare = Square.create 4 4
                      Promotion = None
                      Message = None; Session = None }

            Assert.True(blackMove.Ok, sprintf "%A" blackMove.Error)

            let! whiteAgain =
                service.SubmitMove
                    { GameId = alice.GameId
                      PlayerId = alice.PlayerId
                      Token = alice.Token
                      FromSquare = Square.create 3 1
                      ToSquare = Square.create 3 3
                      Promotion = None
                      Message = None; Session = None }

            Assert.True(whiteAgain.Ok)

            let! illegal =
                service.SubmitMove
                    { GameId = bob.GameId
                      PlayerId = bob.PlayerId
                      Token = bob.Token
                      FromSquare = Square.create 4 4
                      ToSquare = Square.create 4 7
                      Promotion = None
                      Message = None; Session = None }

            Assert.False(illegal.Ok)

            let! after = service.GetGame alice.GameId
            Assert.Equal<string list>([ "e4"; "e5"; "d4" ], after.Value.Moves |> Array.map (fun m -> m.San) |> Array.toList)
            Assert.Equal(Color.Black, after.Value.SideToMove)
            Assert.True(mails.Count >= 5, sprintf "expected several mails, got %d" mails.Count)
        }
    )

[<Fact>]
let ``create a game with a named opponent sends an invitation`` () =
    run (
        async {
            let settings = newSettings ()
            migrate settings
            let mails = ResizeArray<Mail>()
            let service = makeService settings mails

            let alice =
                service.CreateGame
                    { createOpenRequest with
                        OpponentNickname = Some "Bob"
                        OpponentEmail = Some "bob@example.com" }
                |> Async.RunSynchronously
                |> unwrap

            let! view = service.GetGame alice.GameId
            Assert.Equal(GameStatus.Active, view.Value.Status)
            Assert.Equal(2, mails.Count)
            Assert.True(mails |> Seq.exists (fun mail -> mail.To = "bob@example.com"))

            let! validated = service.ValidateToken(alice.GameId, alice.Token)
            Assert.True(validated.IsSome)

            let! rejected = service.ValidateToken(alice.GameId, "forged.token")
            Assert.True(rejected.IsNone)
        }
    )

[<Fact>]
let ``resigning finishes the game in favour of the opponent`` () =
    run (
        async {
            let settings = newSettings ()
            migrate settings
            let mails = ResizeArray<Mail>()
            let service = makeService settings mails

            let alice =
                service.CreateGame
                    { createOpenRequest with
                        OpponentNickname = Some "Bob"
                        OpponentEmail = Some "bob@example.com" }
                |> Async.RunSynchronously
                |> unwrap

            let! response =
                service.Resign
                    { GameId = alice.GameId
                      PlayerId = alice.PlayerId
                      Token = alice.Token
                      Message = None; Session = None }

            Assert.True(response.Ok)
            let! view = service.GetGame alice.GameId
            Assert.Equal(GameStatus.Finished, view.Value.Status)
            Assert.Equal(Some "0-1", view.Value.Result)
        }
    )

[<Fact>]
let ``imports a legacy game file`` () =
    let settings = newSettings ()

    let directory = Path.Combine(Path.GetTempPath(), sprintf "w3chess-games-%s" (Guid.NewGuid().ToString("n")))
    Directory.CreateDirectory directory |> ignore

    let boardAfterE4 =
        String.concat
            ""
            [ "rnbqkbnr"; "pppppppp"; "eeeeeeee"; "eeeeeeee"
              "eeeePeee"; "eeeeeeee"; "PPPPePPP"; "RNBQKBNR" ]

    let content =
        String.concat
            "\n"
            [ "Alice"; "alice@example.com"; "Bob"; "bob@example.com"
              "TOKEN"; "1"; "PE2E4Pe"; boardAfterE4 ]

    File.WriteAllText(Path.Combine(directory, "20260101-1"), content)

    migrate settings

    let count =
        use connection = Db.connect settings.DatabasePath
        let warnings = ImportService.importDirectory connection directory
        Assert.Empty warnings
        connection.Query<GameRow>("SELECT id AS Id FROM games") |> Seq.length

    Assert.Equal(1, count)
