namespace W3Chess.Server.Services

open System
open System.Security.Cryptography
open System.Text
open W3Chess.Contracts
open W3Chess.Server
open W3Chess.Server.Storage

/// Passwordless accounts: request a single-use login link by email, then exchange
/// it for a signed session token.
type AuthService(settings: AppSettings, notifier: Notifier) =
    let connect () = Db.connect settings.DatabasePath
    let sessionLifetime = TimeSpan.FromDays 30.0
    let loginLifetime = TimeSpan.FromMinutes 30.0

    let hashToken (raw: string) =
        use sha = SHA256.Create()
        sha.ComputeHash(Encoding.UTF8.GetBytes raw)
        |> Convert.ToHexString
        |> fun value -> value.ToLowerInvariant()

    let randomToken () =
        RandomNumberGenerator.GetBytes 32
        |> Convert.ToBase64String
        |> fun value -> value.Replace("+", "-").Replace("/", "_").TrimEnd('=')

    let toInfo (account: AccountRow) : AccountInfo =
        { Email = account.Email
          Nickname = account.Nickname
          Language = account.Language }

    member _.RequestLogin(request: LoginRequest) : Async<ActionResponse> =
        async {
            if String.IsNullOrWhiteSpace request.Email || not (request.Email.Contains "@") then
                return { Ok = false; Error = Some "A valid email is required" }
            else
                use connection = connect ()
                let now = Db.nowIso ()

                let account =
                    match AccountRepository.findByEmail connection request.Email with
                    | Some account -> account
                    | None ->
                        let account =
                            { Id = Db.newId ()
                              Email = request.Email
                              Nickname = request.Email.Split('@').[0]
                              Language = request.Language
                              CreatedAt = now }

                        AccountRepository.insert connection account
                        account

                let rawToken = randomToken ()

                LoginTokenRepository.insert
                    connection
                    { TokenHash = hashToken rawToken
                      AccountId = account.Id
                      CreatedAt = now
                      ExpiresAt = DateTimeOffset.UtcNow.Add(loginLifetime).UtcDateTime.ToString("o")
                      UsedAt = "" }

                let url = sprintf "%s/#/auth?token=%s" (settings.PublicBaseUrl.TrimEnd('/')) rawToken
                let subject, body = EmailTemplates.login account.Language url

                do! notifier.Send { To = account.Email; Subject = subject; Body = body }

                return { Ok = true; Error = None }
        }

    member _.CompleteLogin(rawToken: string) : Async<Result<AccountSession, string>> =
        async {
            use connection = connect ()
            let now = Db.nowIso ()
            let tokenHash = hashToken rawToken

            match LoginTokenRepository.findUnused connection tokenHash with
            | None -> return Error "This login link is invalid or has already been used"
            | Some row when row.ExpiresAt < now -> return Error "This login link has expired"
            | Some row ->
                LoginTokenRepository.markUsed connection tokenHash now

                match AccountRepository.findById connection row.AccountId with
                | None -> return Error "Account not found"
                | Some account ->
                    let session = SessionService.create settings.TokenSecret account.Id sessionLifetime
                    return Ok { Session = session; Account = toInfo account }
        }

    member _.GetAccount(session: string) : Async<AccountInfo option> =
        async {
            match SessionService.validate settings.TokenSecret session with
            | None -> return None
            | Some accountId ->
                use connection = connect ()
                return AccountRepository.findById connection accountId |> Option.map toInfo
        }

    /// Creates a single-use login link that lands on `next` (a hash route
    /// fragment such as "game/<id>") when the email belongs to an account.
    /// Returns None when there is no account for the email.
    member _.CreateLoginLink(email: string, next: string) : Async<string option> =
        async {
            use connection = connect ()

            match AccountRepository.findByEmail connection email with
            | None -> return None
            | Some account ->
                let rawToken = randomToken ()

                LoginTokenRepository.insert
                    connection
                    { TokenHash = hashToken rawToken
                      AccountId = account.Id
                      CreatedAt = Db.nowIso ()
                      ExpiresAt = DateTimeOffset.UtcNow.Add(loginLifetime).UtcDateTime.ToString("o")
                      UsedAt = "" }

                let nextPart = if next = "" then "" else "&next=" + next

                return
                    Some
                        (sprintf
                            "%s/#/auth?token=%s%s"
                            (settings.PublicBaseUrl.TrimEnd('/'))
                            rawToken
                            nextPart)
        }
