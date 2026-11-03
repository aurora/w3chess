module W3Chess.Client.ServiceWorker

open Fable.Core

[<Emit("(typeof navigator !== 'undefined' && navigator.serviceWorker) ? navigator.serviceWorker.getRegistrations() : Promise.resolve([])")>]
let private getRegistrationsRaw () : JS.Promise<obj[]> = jsNative

[<Emit("Promise.all($0.map(function (registration) { return registration.unregister(); }))")>]
let private unregisterAllRaw (registrations: obj[]) : JS.Promise<obj> = jsNative

/// How many service workers are registered for this origin. W3Chess itself
/// registers none, so any worker here comes from another application served
/// on the same address and can intercept requests meant for the game.
let countRegistered () : Async<int> =
    async {
        try
            let! registrations = getRegistrationsRaw () |> Async.AwaitPromise
            return registrations.Length
        with _ ->
            return 0
    }

/// Unregisters every service worker on this origin.
let unregisterAll () : Async<unit> =
    async {
        try
            let! registrations = getRegistrationsRaw () |> Async.AwaitPromise
            let! _ = unregisterAllRaw registrations |> Async.AwaitPromise
            return ()
        with _ ->
            return ()
    }
