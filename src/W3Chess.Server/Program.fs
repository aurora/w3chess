module W3Chess.Server.Program

open System
open Microsoft.AspNetCore.Builder
open Microsoft.AspNetCore.Hosting
open Microsoft.Extensions.DependencyInjection
open Giraffe
open Fable.Remoting.Server
open Fable.Remoting.Giraffe
open W3Chess.Server
open W3Chess.Server.Api
open W3Chess.Server.Services
open W3Chess.Server.Storage

let private migrate (settings: AppSettings) =
    use connection = Db.connect settings.DatabasePath
    Schema.migrate connection

let private runImport (settings: AppSettings) (directory: string) =
    use connection = Db.connect settings.DatabasePath
    Schema.migrate connection

    let warnings = ImportService.importDirectory connection directory

    if List.isEmpty warnings then
        printfn "Import finished without warnings."
    else
        warnings |> List.iter (printfn "warning: %s")

[<EntryPoint>]
let main args =
    let builder = WebApplication.CreateBuilder(args)
    let settings = AppSettings.load builder.Configuration

    migrate settings

    if Array.contains "--import" args then
        let index = Array.findIndex ((=) "--import") args
        let directory = if index + 1 < args.Length then args.[index + 1] else settings.LegacyDataDirectory
        runImport settings directory
        0
    else
        if String.IsNullOrWhiteSpace(Environment.GetEnvironmentVariable "ASPNETCORE_URLS") then
            builder.WebHost.UseUrls("http://localhost:5000") |> ignore

        let notifier = Notifier.email settings.Smtp
        let gameService = GameService(settings, notifier)
        let api = ServerApi.create gameService

        let remoting =
            Remoting.createApi ()
            |> Remoting.withRouteBuilder (fun typeName methodName -> sprintf "/api/%s/%s" typeName methodName)
            |> Remoting.fromValue api
            |> Remoting.buildHttpHandler

        let webApp =
            choose [ route "/health" >=> text "ok"; remoting ]

        builder.Services.AddGiraffe() |> ignore
        builder.Services.AddCors() |> ignore

        let app = builder.Build()

        app.UseCors(fun policy -> policy.AllowAnyOrigin().AllowAnyHeader().AllowAnyMethod() |> ignore)
        |> ignore

        app.UseDefaultFiles() |> ignore
        app.UseStaticFiles() |> ignore
        app.UseGiraffe webApp

        printfn "W3Chess server listening (database: %s, public base url: %s)" settings.DatabasePath settings.PublicBaseUrl
        app.Run()
        0
