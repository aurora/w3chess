module W3Chess.Client.Pages.Account

open Feliz
open W3Chess.Contracts
open W3Chess.Client
open W3Chess.Client.Api
open W3Chess.Client.I18n

type AccountProps =
    { Lang: Lang
      OnOpenGame: string -> unit
      OnLogout: unit -> unit }

let private statusLabel (t: T) (game: ArchiveGame) =
    let baseText =
        match game.Status with
        | GameStatus.Waiting -> t.Waiting
        | GameStatus.Active -> t.Active
        | GameStatus.Finished -> t.Finished

    match game.Result with
    | Some result -> baseText + " · " + result
    | None -> baseText

[<ReactComponent>]
let AccountPage (props: AccountProps) =
    let t = I18n.ofLang props.Lang
    let account = Api.currentAccount ()
    let games, setGames = React.useState ([||]: ArchiveGame[])
    let loaded, setLoaded = React.useState (false)
    let email = account |> Option.map (fun value -> value.Email)

    React.useEffect (
        (fun () ->
            match email with
            | Some email ->
                async {
                    try
                        let! list = Api.listGamesForEmail email
                        setGames list
                    with _ -> ()

                    setLoaded true
                }
                |> Async.StartImmediate
            | None -> setLoaded true),
        [| box email |]
    )

    let identityRow (label: string) (value: string) =
        Html.div [
            prop.className "flex items-center justify-between gap-4"
            prop.children [
                Html.span [
                    prop.className "text-xs uppercase tracking-wide text-on-surface-variant"
                    prop.text label
                ]
                Html.span [ prop.className "font-mono text-sm text-on-surface"; prop.text value ]
            ]
        ]

    Html.div [
        prop.className "w-full max-w-3xl mx-auto px-6 py-8 flex flex-col gap-6"
        prop.children [
            Html.h1 [
                prop.className "text-3xl font-extrabold tracking-tight text-primary"
                prop.text t.Account
            ]

            match account with
            | None ->
                Html.div [
                    prop.className "rounded border border-outline-variant bg-surface-container-low p-6 text-sm text-on-surface-variant"
                    prop.text t.LogInFirst
                ]
            | Some account ->
                yield
                    Html.div [
                        prop.className
                            "rounded-lg border border-outline-variant bg-surface-container-low p-6 flex flex-col gap-3"
                        prop.children [
                            identityRow t.YourNickname account.Nickname
                            identityRow t.YourEmail account.Email
                            identityRow t.Language account.Language

                            Html.button [
                                prop.type'.button
                                prop.className
                                    "h-11 rounded border border-outline-variant text-on-surface hover:border-error hover:text-error text-sm self-start px-4"
                                prop.onClick (fun _ -> props.OnLogout ())
                                prop.text t.Logout
                            ]
                        ]
                    ]

                yield
                    Html.div [
                        prop.className
                            "rounded-lg border border-outline-variant bg-surface-container-low p-6 flex flex-col gap-3"
                        prop.children [
                            Html.h2 [
                                prop.className "text-lg font-bold text-on-surface"
                                prop.text t.YourGames
                            ]

                            if not loaded then
                                Html.p [ prop.className "text-sm text-on-surface-variant"; prop.text t.Loading ]
                            elif Array.isEmpty games then
                                Html.p [ prop.className "text-sm text-on-surface-variant"; prop.text t.NoGames ]
                            else
                                for game in games do
                                    yield
                                        Html.div [
                                            prop.key game.GameId
                                            prop.className
                                                "flex items-center justify-between gap-3 rounded border border-outline-variant px-3 py-2"
                                            prop.children [
                                                Html.div [
                                                    prop.className "flex flex-col"
                                                    prop.children [
                                                        Html.span [
                                                            prop.className "font-mono text-xs text-on-surface-variant"
                                                            prop.text game.GameId
                                                        ]
                                                        Html.span [
                                                            prop.className "text-sm text-on-surface"
                                                            prop.text
                                                                (sprintf
                                                                    "%s vs %s"
                                                                    (defaultArg game.White "?")
                                                                    (defaultArg game.Black "?"))
                                                        ]
                                                        Html.span [
                                                            prop.className "text-xs text-on-surface-variant"
                                                            prop.text (statusLabel t game)
                                                        ]
                                                    ]
                                                ]

                                                Html.button [
                                                    prop.type'.button
                                                    prop.className
                                                        "h-8 px-3 rounded border border-primary text-primary text-sm"
                                                    prop.onClick (fun _ -> props.OnOpenGame game.GameId)
                                                    prop.text t.Open
                                                ]
                                            ]
                                        ]
                        ]
                    ]
        ]
    ]
