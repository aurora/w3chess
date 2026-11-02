module W3Chess.Client.Api

open Fable.Remoting.Client
open Browser.WebStorage
open W3Chess.Domain
open W3Chess.Contracts

/// Typed Fable.Remoting client. The shared `ApiRoutes.build` keeps the client
/// and server routes aligned; Vite proxies `/api` to Kestrel.
let api: IW3ChessApi =
    Remoting.createApi ()
    |> Remoting.withRouteBuilder ApiRoutes.build
    |> Remoting.buildProxy<IW3ChessApi>

let private storageKey (gameId: string) = "w3chess.credentials." + gameId

let saveCredentials (credentials: PlayerCredentials) =
    let color =
        match credentials.Color with
        | White -> "w"
        | Black -> "b"

    localStorage.setItem(
        storageKey credentials.GameId,
        String.concat "|" [ credentials.GameId; credentials.PlayerId; credentials.Token; color ]
    )

let loadCredentials (gameId: string) : PlayerCredentials option =
    let value = localStorage.getItem (storageKey gameId)

    if isNull value then
        None
    else
        match value.Split('|') with
        | parts when parts.Length = 4 && parts.[0] = gameId ->
            Some
                { GameId = parts.[0]
                  PlayerId = parts.[1]
                  Token = parts.[2]
                  Color = (if parts.[3] = "b" then Black else White) }
        | _ -> None
