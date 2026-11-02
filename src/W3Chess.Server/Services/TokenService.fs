namespace W3Chess.Server.Services

open System
open System.Security.Cryptography
open System.Text

/// Signed, stateless access tokens for email links. The token has the shape
/// `<playerId>.<base64url(hmac)>`, so it encodes the player and validates
/// against the per-application secret without a database round-trip.
module TokenService =
    let private base64Url (bytes: byte[]) =
        Convert.ToBase64String(bytes).Replace("+", "-").Replace("/", "_").TrimEnd('=')

    let private sign (secret: string) (payload: string) =
        use hmac = new HMACSHA256(Encoding.UTF8.GetBytes secret)
        hmac.ComputeHash(Encoding.UTF8.GetBytes payload) |> base64Url

    let private constantTimeEquals (a: string) (b: string) =
        let ab = Encoding.UTF8.GetBytes a
        let bb = Encoding.UTF8.GetBytes b
        if ab.Length <> bb.Length then false
        else
            let mutable diff = 0
            for i in 0 .. ab.Length - 1 do
                diff <- diff ||| (int ab.[i] ^^^ int bb.[i])
            diff = 0

    let create (secret: string) (gameId: string) (playerId: string) =
        playerId + "." + sign secret (gameId + ":" + playerId)

    /// Returns the player id when the token is valid for the game.
    let validate (secret: string) (gameId: string) (token: string) =
        match token.Split([| '.' |], StringSplitOptions.RemoveEmptyEntries) with
        | [| playerId; _ |] ->
            let expected = create secret gameId playerId
            if constantTimeEquals expected token then Some playerId else None
        | _ -> None
