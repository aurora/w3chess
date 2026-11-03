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
    | Auth of token: string * next: string option
    | Account

let private parseQueryParam (name: string) (query: string) =
    query.Split('&')
    |> Array.tryPick (fun pair ->
        match pair.Split('=') with
        | [| key; value |] when key = name -> Some value
        | _ -> None)

let private parseRoute (hash: string) : Route =
    let hash = if hash.StartsWith "#" then hash.Substring 1 else hash

    let path, query =
        match hash.IndexOf('?') with
        | -1 -> hash, ""
        | index -> hash.Substring(0, index), hash.Substring(index + 1)

    let segments = path.Split('/') |> Array.filter (fun segment -> segment <> "")

    match segments with
    | [| "game"; gameId |] -> Game(gameId, parseQueryParam "token" query)
    | [| "auth" |] ->
        match parseQueryParam "token" query with
        | Some token -> Auth(token, parseQueryParam "next" query)
        | None -> Lobby
    | [| "account" |] -> Account
    | _ -> Lobby

let private openGame (gameId: string) = window.location.hash <- "#/game/" + gameId
let private goLobby () = window.location.hash <- "#/"
let private goAccount () = window.location.hash <- "#/account"

let private browserLanguage () : string =
    let language: obj = window?navigator?language
    if isNull language then "en" else string language

/// `?lang=en|de`, either in the search string or in the hash query.
let private languageOverride () =
    let fromSearch = parseQueryParam "lang" (window.location.search.TrimStart('?'))

    let fromHash =
        let hash = window.location.hash

        match hash.IndexOf('?') with
        | -1 -> None
        | index -> parseQueryParam "lang" (hash.Substring(index + 1))

    match fromSearch, fromHash with
    | Some code, _ -> Some(I18n.ofCode code)
    | None, Some code -> Some(I18n.ofCode code)
    | None, None -> None

let private initialLang () =
    match languageOverride () with
    | Some lang -> lang
    | None ->
        match localStorage.getItem "w3chess.lang" with
        | null -> I18n.ofCode (browserLanguage ())
        | stored -> I18n.ofCode stored

[<ReactComponent>]
let App () =
    let lang, setLang = React.useState (initialLang ())

    let theme, setTheme =
        React.useState (
            match localStorage.getItem "w3chess.theme" with
            | null -> Dark
            | stored -> Theme.ofCode stored
        )

    let route, setRoute = React.useState (parseRoute window.location.hash)
    let account, setAccount = React.useState (Api.currentAccount ())
    let authError, setAuthError = React.useState (None: string option)
    let rogueWorker, setRogueWorker = React.useState (false)
    let loginEmail, setLoginEmail = React.useState ("")
    let loginNotice, setLoginNotice = React.useState (false)
    let loginError, setLoginError = React.useState (None: string option)
    let t = I18n.ofLang lang

    React.useEffect (
        (fun () ->
            let handler = fun (_: Browser.Types.Event) -> setRoute (parseRoute window.location.hash)
            window.addEventListener ("hashchange", handler)),
        [||]
    )

    // W3Chess registers no service worker; one on this origin comes from
    // another application and can swallow requests meant for the server.
    React.useEffect (
        (fun () ->
            async {
                let! count = ServiceWorker.countRegistered ()
                if count > 0 then setRogueWorker true
            }
            |> Async.StartImmediate),
        [||]
    )

    // Validate a stored session once on start-up.
    React.useEffect (
        (fun () ->
            match Api.currentSession () with
            | Some session ->
                async {
                    try
                        let! current = Api.getAccount session

                        match current with
                        | Some value -> setAccount (Some value)
                        | None ->
                            Api.clearSession ()
                            setAccount None
                    with _ -> ()
                }
                |> Async.StartImmediate
            | None -> ()),
        [||]
    )

    let authToken, authNext =
        match route with
        | Auth(token, next) -> token, next
        | _ -> "", None

    // Complete a magic-link login when the auth route carries a token.
    React.useEffect (
        (fun () ->
            if authToken = "" then
                ()
            else
                async {
                    try
                        let! result = Api.completeLogin authToken

                        match result with
                        | Ok session ->
                            Api.saveSession session
                            setAccount (Some session.Account)
                            setAuthError None

                            match authNext with
                            | Some target when target <> "" -> window.location.hash <- "#/" + target
                            | _ -> goLobby ()
                        | Error message -> setAuthError (Some (I18n.ofError t message))
                    with ex ->
                        setAuthError (Some (I18n.ofError t ex.Message))
                }
                |> Async.StartImmediate),
        [| box authToken; box authNext |]
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

    let requestLogin () =
        async {
            try
                let! response = Api.requestLogin { Email = loginEmail; Language = I18n.code lang }

                if response.Ok then
                    setLoginNotice true
                    setLoginError None
                else
                    setLoginError response.Error
            with ex ->
                setLoginError (Some ex.Message)
        }
        |> Async.StartImmediate

    let logout () =
        Api.clearSession ()
        setAccount None

    let accountArea =
        match account with
        | Some value ->
            Html.div [
                prop.className "flex items-center gap-2"
                prop.children [
                    navButton (route = Account) value.Nickname goAccount
                    navButton false t.Logout logout
                ]
            ]
        | None ->
            Html.div [
                prop.className "flex flex-col items-end gap-1"
                prop.children [
                    Html.div [
                        prop.className "flex items-center gap-1"
                        prop.children [
                            Html.input [
                                prop.className
                                    "h-9 w-40 px-2 rounded border border-outline-variant bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:border-primary"
                                prop.placeholder t.YourEmail
                                prop.value loginEmail
                                prop.onChange setLoginEmail
                            ]
                            navButton false t.Login requestLogin
                        ]
                    ]
                    (if loginNotice then
                         Html.span [ prop.className "text-[10px] text-secondary"; prop.text t.CheckEmail ]
                     else
                         Html.none)
                    (match loginError with
                     | Some message -> Html.span [ prop.className "text-[10px] text-error"; prop.text message ]
                     | None -> Html.none)
                ]
            ]

    Html.div [
        prop.className "min-h-screen flex flex-col bg-background text-on-background"
        prop.children [
            if rogueWorker then
                Html.div [
                    prop.className "border-b border-error bg-error/10 px-6 py-2"
                    prop.children [
                        Html.div [
                            prop.className "max-w-6xl mx-auto flex flex-wrap items-center justify-center gap-3 text-sm text-error"
                            prop.children [
                                Html.span [ prop.text t.RogueWorkerFound ]
                                Html.button [
                                    prop.type'.button
                                    prop.className "px-3 py-1 rounded border border-error font-semibold hover:bg-error hover:text-on-error"
                                    prop.onClick (fun _ ->
                                        async {
                                            do! ServiceWorker.unregisterAll ()
                                            window.location.reload ()
                                        }
                                        |> Async.StartImmediate)
                                    prop.text t.RogueWorkerRemove
                                ]
                            ]
                        ]
                    ]
                ]

            Html.header [
                prop.className "border-b border-outline-variant bg-surface-container-low"
                prop.children [
                    Html.div [
                        prop.className "max-w-6xl mx-auto px-6 py-4 flex items-center justify-between gap-4"
                        prop.children [
                            Html.div [
                                prop.className "flex items-center gap-3"
                                prop.children [
                                    Html.span [ prop.className "text-2xl"; prop.text "\u265A" ]
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
                                    accountArea
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
                    | Account ->
                        Account.AccountPage
                            { Lang = lang
                              OnOpenGame = openGame
                              OnLogout = fun () ->
                                  logout ()
                                  goLobby () }
                    | Auth _ ->
                        Html.div [
                            prop.className
                                "max-w-6xl mx-auto px-6 py-16 flex flex-col gap-3 items-center text-center"
                            prop.children [
                                Html.span [ prop.className "text-on-surface"; prop.text t.Loading ]
                                (match authError with
                                 | Some message -> Html.p [ prop.className "text-sm text-error"; prop.text message ]
                                 | None -> Html.none)
                            ]
                        ]
                ]
            ]
        ]
    ]

let root = ReactDOM.createRoot (document.getElementById "app")
root.render (App())
