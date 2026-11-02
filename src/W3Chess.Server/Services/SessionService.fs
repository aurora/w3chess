namespace W3Chess.Server.Services

open System
open System.Security.Cryptography
open System.Text

/// Signed, stateless account session tokens with the shape
/// `<accountId>.<expiryUnix>.<signature>`. The client stores one and sends it as
/// a bearer credential.
module SessionService =
    let private base64Url (bytes: byte[]) =
        Convert.ToBase64String(bytes).Replace("+", "-").Replace("/", "_").TrimEnd('=')

    let private sign (secret: string) (payload: string) =
        use hmac = new HMACSHA256(Encoding.UTF8.GetBytes secret)
        hmac.ComputeHash(Encoding.UTF8.GetBytes payload) |> base64Url

    let private constantTimeEquals (a: string) (b: string) =
        let ab = Encoding.UTF8.GetBytes a
        let bb = Encoding.UTF8.GetBytes b

        if ab.Length <> bb.Length then
            false
        else
            let mutable diff = 0

            for i in 0 .. ab.Length - 1 do
                diff <- diff ||| (int ab.[i] ^^^ int bb.[i])

            diff = 0

    let create (secret: string) (accountId: string) (lifetime: TimeSpan) =
        let expiry = DateTimeOffset.UtcNow.Add(lifetime).ToUnixTimeSeconds()
        sprintf "%s.%d.%s" accountId expiry (sign secret (sprintf "%s:%d" accountId expiry))

    let validate (secret: string) (token: string) : string option =
        match token.Split('.') with
        | [| accountId; expiryText; signature |] ->
            match Int64.TryParse expiryText with
            | true, expiry when expiry > DateTimeOffset.UtcNow.ToUnixTimeSeconds() ->
                let expected = sign secret (sprintf "%s:%s" accountId expiryText)
                if constantTimeEquals expected signature then Some accountId else None
            | _ -> None
        | _ -> None
