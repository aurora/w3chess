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

    let authService = AuthService(settings, notifier)
    GameService(settings, authService, notifier, GameEvents())

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
let ``joining a game created as black assigns white to the joiner`` () =
    run (
        async {
            let settings = newSettings ()
            migrate settings
            let mails = ResizeArray<Mail>()
            let service = makeService settings mails

            let alice =
                service.CreateGame { createOpenRequest with ColorChoice = ColorChoice.AsBlack }
                |> Async.RunSynchronously
                |> unwrap

            Assert.Equal(Color.Black, alice.Color)

            let bob =
                service.JoinGame
                    { GameId = alice.GameId
                      Nickname = "Bob"
                      Email = "bob@example.com"
                      Message = None
                      Language = "en"; Session = None }
                |> Async.RunSynchronously
                |> unwrap

            Assert.Equal(Color.White, bob.Color)

            let! view = service.GetGame alice.GameId
            Assert.Equal(Some "Bob", view.Value.White |> Option.map (fun p -> p.Nickname))
            Assert.Equal(Some "Alice", view.Value.Black |> Option.map (fun p -> p.Nickname))
            Assert.Equal(Color.White, view.Value.SideToMove)

            // White (the joiner) opens the game.
            let! firstMove =
                service.SubmitMove
                    { GameId = alice.GameId
                      PlayerId = bob.PlayerId
                      Token = bob.Token
                      FromSquare = Square.create 4 1
                      ToSquare = Square.create 4 3
                      Promotion = None
                      Message = None; Session = None }

            Assert.True(firstMove.Ok, sprintf "%A" firstMove.Error)
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
let ``a draw offer can be accepted by the opponent but not the offerer`` () =
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

            let bob =
                service.JoinGame
                    { GameId = alice.GameId
                      Nickname = "Bob"
                      Email = "bob@example.com"
                      Message = None
                      Language = "en"; Session = None }
                |> Async.RunSynchronously
                |> unwrap

            let! offered =
                service.OfferDraw
                    { GameId = alice.GameId
                      PlayerId = alice.PlayerId
                      Token = alice.Token
                      Message = None; Session = None }

            Assert.True(offered.Ok, sprintf "%A" offered.Error)

            let! view = service.GetGame alice.GameId
            Assert.True(view.Value.DrawOfferPending)
            Assert.Equal(Some alice.PlayerId, view.Value.DrawOfferBy)
            Assert.True(
                mails |> Seq.exists (fun mail -> mail.To = "bob@example.com" && mail.Subject.Contains "Draw offer"),
                sprintf "expected a draw offer mail for Bob, got %A" mails
            )

            let! self =
                service.AcceptDraw
                    { GameId = alice.GameId
                      PlayerId = alice.PlayerId
                      Token = alice.Token
                      Message = None; Session = None }

            Assert.False(self.Ok)

            let! accepted =
                service.AcceptDraw
                    { GameId = bob.GameId
                      PlayerId = bob.PlayerId
                      Token = bob.Token
                      Message = None; Session = None }

            Assert.True(accepted.Ok, sprintf "%A" accepted.Error)

            let! finished = service.GetGame alice.GameId
            Assert.Equal(GameStatus.Finished, finished.Value.Status)
            Assert.Equal(Some "1/2-1/2", finished.Value.Result)
            Assert.Equal(None, finished.Value.DrawOfferBy)
        }
    )

[<Fact>]
let ``a session player can offer a draw`` () =
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

            let _ =
                service.JoinGame
                    { GameId = alice.GameId
                      Nickname = "Bob"
                      Email = "bob@example.com"
                      Message = None
                      Language = "en"; Session = None }
                |> Async.RunSynchronously
                |> unwrap

            let accountId = Db.newId ()

            use connection = Db.connect settings.DatabasePath

            AccountRepository.insert
                connection
                { Id = accountId
                  Email = "alice@example.com"
                  Nickname = "Alice"
                  Language = "en"
                  CreatedAt = Db.nowIso () }

            let session = SessionService.create settings.TokenSecret accountId (TimeSpan.FromHours 1.0)

            let! offered =
                service.OfferDraw
                    { GameId = alice.GameId
                      PlayerId = ""
                      Token = ""
                      Message = None; Session = Some session }

            Assert.True(offered.Ok, sprintf "%A" offered.Error)

            let! view = service.GetGame alice.GameId
            Assert.True(view.Value.DrawOfferPending, "the offer must be stored for session players")
            Assert.Equal(Some alice.PlayerId, view.Value.DrawOfferBy)
        }
    )

[<Fact>]
let ``a session player resolves to their game credentials`` () =
    run (
        async {
            let settings = newSettings ()
            migrate settings
            let service = makeService settings (ResizeArray<Mail>())

            let alice =
                service.CreateGame createOpenRequest
                |> Async.RunSynchronously
                |> unwrap

            let bob =
                service.JoinGame
                    { GameId = alice.GameId
                      Nickname = "Bob"
                      Email = "bob@example.com"
                      Message = None
                      Language = "en"; Session = None }
                |> Async.RunSynchronously
                |> unwrap

            use connection = Db.connect settings.DatabasePath

            for playerId, email, expectedColor in
                [ alice.PlayerId, "alice@example.com", Color.White
                  bob.PlayerId, "bob@example.com", Color.Black ] do
                AccountRepository.insert
                    connection
                    { Id = playerId
                      Email = email
                      Nickname = email
                      Language = "en"
                      CreatedAt = Db.nowIso () }

                let session = SessionService.create settings.TokenSecret playerId (TimeSpan.FromHours 1.0)
                let! resolved = service.ValidateSession(alice.GameId, session)

                Assert.Equal(Some alice.GameId, resolved |> Option.map (fun c -> c.GameId))
                Assert.Equal(Some playerId, resolved |> Option.map (fun c -> c.PlayerId))
                Assert.Equal(Some expectedColor, resolved |> Option.map (fun c -> c.Color))
        }
    )

[<Fact>]
let ``threefold repetition ends the game in a draw`` () =
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

            let bob =
                service.JoinGame
                    { GameId = alice.GameId
                      Nickname = "Bob"
                      Email = "bob@example.com"
                      Message = None
                      Language = "en"; Session = None }
                |> Async.RunSynchronously
                |> unwrap

            // 1.Nf3 Nf6 2.Ng1 Ng8 3.Nf3 Nf6 4.Ng1 Ng8 brings the start
            // position back for the third time.
            let plies =
                [ alice, 6, 21
                  bob, 62, 45
                  alice, 21, 6
                  bob, 45, 62
                  alice, 6, 21
                  bob, 62, 45
                  alice, 21, 6
                  bob, 45, 62 ]

            for credentials, fromSquare, toSquare in plies do
                let! response =
                    service.SubmitMove
                        { GameId = alice.GameId
                          PlayerId = credentials.PlayerId
                          Token = credentials.Token
                          FromSquare = fromSquare
                          ToSquare = toSquare
                          Promotion = None
                          Message = None; Session = None }

                Assert.True(response.Ok, sprintf "%A" response.Error)

            let! finished = service.GetGame alice.GameId
            Assert.Equal(GameStatus.Finished, finished.Value.Status)
            Assert.Equal(Some "1/2-1/2", finished.Value.Result)
        }
    )

[<Fact>]
let ``retention deletes games not updated within the configured days`` () =
    run (
        async {
            let settings = { newSettings () with GameRetentionDays = 30 }
            migrate settings
            let service = makeService settings (ResizeArray<Mail>())

            let alice =
                service.CreateGame
                    { createOpenRequest with
                        OpponentNickname = Some "Bob"
                        OpponentEmail = Some "bob@example.com" }
                |> Async.RunSynchronously
                |> unwrap

            // Backdate the game below the retention cutoff.
            use connection = Db.connect settings.DatabasePath

            connection.Execute(
                "UPDATE games SET updated_at = @updatedAt WHERE id = @id",
                {| updatedAt = Retention.cutoffIso 40; id = alice.GameId |}
            )
            |> ignore

            let! deleted = Retention.cleanupExpired settings
            Assert.Equal(1, deleted)

            let! view = service.GetGame alice.GameId
            Assert.True(view.IsNone)
        }
    )

[<Fact>]
let ``a retention of zero days deletes nothing`` () =
    run (
        async {
            let settings = newSettings () // GameRetentionDays = 0
            migrate settings
            let service = makeService settings (ResizeArray<Mail>())

            let alice =
                service.CreateGame createOpenRequest
                |> Async.RunSynchronously
                |> unwrap

            use connection = Db.connect settings.DatabasePath

            connection.Execute(
                "UPDATE games SET updated_at = @updatedAt WHERE id = @id",
                {| updatedAt = Retention.cutoffIso 40; id = alice.GameId |}
            )
            |> ignore

            let! deleted = Retention.cleanupExpired settings
            Assert.Equal(0, deleted)

            let! view = service.GetGame alice.GameId
            Assert.True(view.IsSome)
        }
    )

[<Fact>]
let ``game mails for account holders include a login link`` () =
    run (
        async {
            let settings = newSettings ()
            migrate settings
            let mails = ResizeArray<Mail>()
            let service = makeService settings mails

            use connection = Db.connect settings.DatabasePath

            AccountRepository.insert
                connection
                { Id = Db.newId ()
                  Email = "alice@example.com"
                  Nickname = "Alice"
                  Language = "en"
                  CreatedAt = Db.nowIso () }

            let alice =
                service.CreateGame
                    { createOpenRequest with
                        OpponentNickname = Some "Bob"
                        OpponentEmail = Some "bob@example.com" }
                |> Async.RunSynchronously
                |> unwrap

            // Alice has an account: her mail gains a login link that lands on
            // the game. Bob has none: his invitation keeps only the game link.
            let aliceMail = mails |> Seq.find (fun mail -> mail.To = "alice@example.com")
            Assert.Contains("#/auth?token=", aliceMail.Body)
            Assert.Contains(sprintf "next=game/%s" alice.GameId, aliceMail.Body)

            let bobMail = mails |> Seq.find (fun mail -> mail.To = "bob@example.com")
            Assert.DoesNotContain("#/auth?token=", bobMail.Body)

            // An unknown email resolves to no link.
            let! noLink =
                AuthService(settings, { Send = fun _ -> async { () } }).CreateLoginLink("nobody@example.com", "game/x")

            Assert.True(noLink.IsNone)
        }
    )

[<Fact>]
let ``retention purges expired and used login tokens`` () =
    run (
        async {
            let settings = newSettings ()
            migrate settings

            use connection = Db.connect settings.DatabasePath
            let accountId = Db.newId ()

            AccountRepository.insert
                connection
                { Id = accountId
                  Email = "alice@example.com"
                  Nickname = "Alice"
                  Language = "en"
                  CreatedAt = Db.nowIso () }

            LoginTokenRepository.insert
                connection
                { TokenHash = "expired"; AccountId = accountId; CreatedAt = Db.nowIso (); ExpiresAt = "2000-01-01T00:00:00.0000000Z"; UsedAt = "" }

            LoginTokenRepository.insert
                connection
                { TokenHash = "used"; AccountId = accountId; CreatedAt = Db.nowIso (); ExpiresAt = Retention.cutoffIso 1; UsedAt = Db.nowIso () }

            LoginTokenRepository.insert
                connection
                { TokenHash = "live"; AccountId = accountId; CreatedAt = Db.nowIso (); ExpiresAt = Retention.cutoffIso -1; UsedAt = "" }

            let! purged = Retention.purgeLoginTokens settings
            Assert.Equal(2, purged)

            Assert.True(LoginTokenRepository.findUnused connection "live" |> Option.isSome)
            Assert.True(LoginTokenRepository.findUnused connection "expired" |> Option.isNone)
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
