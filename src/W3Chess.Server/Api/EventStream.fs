namespace W3Chess.Server.Api

open System
open System.Threading.Tasks
open Microsoft.AspNetCore.Http
open Microsoft.Extensions.Primitives
open Giraffe
open W3Chess.Server.Services

/// Server-Sent Events endpoint. A player subscribes to a game with the token
/// from their email link; the server then streams "updated" pokes whenever the
/// game changes.
module EventStream =
    let handler (gameEvents: GameEvents) (tokenSecret: string) (gameId: string) : HttpHandler =
        fun _next (ctx: HttpContext) ->
            task {
                let token =
                    match ctx.TryGetQueryStringValue "token" with
                    | Some value -> value
                    | None -> ""

                match TokenService.validate tokenSecret gameId token with
                | None ->
                    ctx.Response.StatusCode <- 401
                    do! ctx.Response.WriteAsync("Invalid access token")
                    return Some ctx
                | Some _ ->
                    let cancellationToken = ctx.RequestAborted

                    ctx.Response.StatusCode <- 200
                    ctx.Response.ContentType <- "text/event-stream"
                    ctx.Response.Headers.["Cache-Control"] <- StringValues "no-cache"
                    ctx.Response.Headers.["X-Accel-Buffering"] <- StringValues "no"

                    let subscriptionId, channel = gameEvents.Subscribe gameId

                    try
                        try
                            do! ctx.Response.WriteAsync(": connected\n\n", cancellationToken)
                            do! ctx.Response.Body.FlushAsync(cancellationToken)

                            while not cancellationToken.IsCancellationRequested do
                                let readTask = channel.Reader.ReadAsync(cancellationToken).AsTask()
                                let! completed = Task.WhenAny(readTask, Task.Delay(15000, cancellationToken))

                                if obj.ReferenceEquals(completed, readTask) then
                                    let! eventName = readTask
                                    do! ctx.Response.WriteAsync(sprintf "data: %s\n\n" eventName, cancellationToken)
                                else
                                    do! ctx.Response.WriteAsync(": ping\n\n", cancellationToken)

                                do! ctx.Response.Body.FlushAsync(cancellationToken)

                            return Some ctx
                        with
                        | :? OperationCanceledException -> return Some ctx
                        | _ -> return Some ctx
                    finally
                        gameEvents.Unsubscribe(gameId, subscriptionId)
            }
