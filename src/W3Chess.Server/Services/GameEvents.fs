namespace W3Chess.Server.Services

open System
open System.Collections.Concurrent
open System.Threading.Channels

/// In-memory publish/subscribe for game updates. The SSE endpoint subscribes per
/// game (after validating the player's token) and forwards events to the browser.
type GameEvents() =
    let subscribers = ConcurrentDictionary<string, ConcurrentDictionary<Guid, Channel<string>>>()

    member _.Subscribe(gameId: string) : Guid * Channel<string> =
        let id = Guid.NewGuid()
        let options = BoundedChannelOptions(64)
        options.FullMode <- BoundedChannelFullMode.DropOldest
        let channel = Channel.CreateBounded<string>(options)
        subscribers.GetOrAdd(gameId, fun _ -> ConcurrentDictionary<Guid, Channel<string>>()).[id] <- channel
        id, channel

    member _.Unsubscribe(gameId: string, id: Guid) =
        match subscribers.TryGetValue gameId with
        | true, channels ->
            channels.TryRemove id |> ignore

            if channels.IsEmpty then
                subscribers.TryRemove gameId |> ignore
        | _ -> ()

    member _.Publish(gameId: string, eventName: string) =
        match subscribers.TryGetValue gameId with
        | true, channels ->
            for entry in channels do
                entry.Value.Writer.TryWrite eventName |> ignore
        | _ -> ()
