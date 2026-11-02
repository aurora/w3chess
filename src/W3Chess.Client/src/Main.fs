module W3Chess.Client.Main

open Feliz
open Fable.Core.JsInterop
open Browser
open Browser.Dom
open Browser.WebStorage
open W3Chess.Client
open W3Chess.Client.I18n
open W3Chess.Client.Theme
open W3Chess.Client.Pages

type Route =
    | Lobby
    | Game of gameId: string * token: string option

let private parseToken (query: string) =
    query.Split('&')
    |> Array.tryPick (fun pair ->
        match pair.Split('=') with
        | [| "token"; value |] -> Some value
        | _ -> None)

let private parseRoute (hash: string) : Route =
    let hash = if hash.StartsWith "#" then hash.Substring 1 else hash

    let path, query =
        match hash.IndexOf('?') with
        | -1 -> hash, ""
        | index -> hash.Substring(0, index), hash.Substring(index + 1)

    let segments = path.Split('/') |> Array.filter (fun segment -> segment <> "")

    match segments with
    | [| "game"; gameId |] -> Game(gameId, parseToken query)
    | _ -> Lobby

let private openGame (gameId: string) = window.location.hash <- "#/game/" + gameId
let private goLobby () = window.location.hash <- "#/"

let private browserLanguage () : string =
    let language: obj = window?navigator?language
    if isNull language then "en" else string language

[<ReactComponent>]
let App () =
    let lang, setLang =
        React.useState (
            match localStorage.getItem "w3chess.lang" with
            | null -> I18n.ofCode (browserLanguage ())
            | stored -> I18n.ofCode stored
        )

    let theme, setTheme =
        React.useState (
            match localStorage.getItem "w3chess.theme" with
            | null -> Dark
            | stored -> Theme.ofCode stored
        )

    let route, setRoute = React.useState (parseRoute window.location.hash)
    let t = I18n.ofLang lang

    React.useEffect (
        (fun () ->
            let handler = fun (_: Browser.Types.Event) -> setRoute (parseRoute window.location.hash)
            window.addEventListener ("hashchange", handler)),
        [||]
    )

    React.useEffect (
        (fun () ->
            let classes = document.documentElement.classList

            if theme = Dark then classes.add "dark" else classes.remove "dark"

            localStorage.setItem ("w3chess.theme", Theme.toCode theme)),
        [| box theme |]
    )

    React.useEffect (
        (fun () ->
            localStorage.setItem ("w3chess.lang", I18n.code lang)
            document.documentElement.setAttribute ("lang", I18n.code lang)),
        [| box lang |]
    )

    let navButton (isActive: bool) (label: string) (onClick: unit -> unit) =
        Html.button [
            prop.type'.button
            prop.className (
                "px-3 py-1.5 rounded text-sm font-semibold "
                + (if isActive then "bg-primary text-on-primary" else "text-on-surface-variant hover:text-on-surface")
            )
            prop.onClick (fun _ -> onClick ())
            prop.text label
        ]

    Html.div [
        prop.className "min-h-screen flex flex-col bg-background text-on-background"
        prop.children [
            Html.header [
                prop.className "border-b border-outline-variant bg-surface-container-low"
                prop.children [
                    Html.div [
                        prop.className "max-w-6xl mx-auto px-6 py-4 flex items-center justify-between gap-4"
                        prop.children [
                            Html.div [
                                prop.className "flex items-center gap-3"
                                prop.children [
                                    Html.span [
                                        prop.className "text-2xl"
                                        prop.text "\u265A"
                                    ]
                                    Html.div [
                                        prop.children [
                                            Html.div [
                                                prop.className "font-extrabold tracking-tight text-lg text-on-surface"
                                                prop.text t.AppName
                                            ]
                                            Html.div [
                                                prop.className "text-[10px] uppercase tracking-widest text-on-surface-variant"
                                                prop.text t.Tagline
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                            Html.div [
                                prop.className "flex items-center gap-2"
                                prop.children [
                                    navButton (route = Lobby) t.Lobby goLobby
                                    Html.button [
                                        prop.type'.button
                                        prop.className
                                            "px-3 py-1.5 rounded border border-outline-variant text-sm text-on-surface-variant hover:text-on-surface"
                                        prop.onClick (fun _ -> setTheme (if theme = Dark then Light else Dark))
                                        prop.text (if theme = Dark then t.LightMode else t.DarkMode)
                                    ]
                                    Html.div [
                                        prop.className "flex items-center rounded border border-outline-variant overflow-hidden"
                                        prop.children [
                                            navButton (lang = En) "EN" (fun () -> setLang En)
                                            navButton (lang = De) "DE" (fun () -> setLang De)
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]

            Html.main [
                prop.className "flex-1"
                prop.children [
                    match route with
                    | Lobby -> Lobby.Lobby { Lang = lang; OnOpenGame = openGame }
                    | Game(gameId, token) ->
                        Game.GamePage
                            { GameId = gameId
                              Token = token
                              Lang = lang
                              OnBack = goLobby }
                ]
            ]
        ]
    ]

let root = ReactDOM.createRoot (document.getElementById "app")
root.render (App())
