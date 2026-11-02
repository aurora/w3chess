module W3Chess.Client.Pages.Lobby

open Feliz
open W3Chess.Contracts
open W3Chess.Client
open W3Chess.Client.Api
open W3Chess.Client.I18n

type LobbyProps =
    { Lang: Lang
      OnOpenGame: string -> unit }

let private field (label: string) (value: string) (onChange: string -> unit) (placeholder: string) =
    Html.label [
        prop.className "flex flex-col gap-1"
        prop.children [
            Html.span [ prop.className "text-xs uppercase tracking-wide text-on-surface-variant"; prop.text label ]
            Html.input [
                prop.className
                    "h-11 px-3 rounded border border-outline-variant bg-surface-container-lowest text-on-surface focus:outline-none focus:border-primary"
                prop.value value
                prop.placeholder placeholder
                prop.onChange onChange
            ]
        ]
    ]

[<ReactComponent>]
let Lobby (props: LobbyProps) =
    let t = I18n.ofLang props.Lang
    let nickname, setNickname = React.useState ("")
    let email, setEmail = React.useState ("")
    let color, setColor = React.useState (ColorChoice.RandomColor)
    let opponentNick, setOpponentNick = React.useState ("")
    let opponentEmail, setOpponentEmail = React.useState ("")
    let message, setMessage = React.useState ("")
    let resumeId, setResumeId = React.useState ("")
    let digestEmail, setDigestEmail = React.useState ("")
    let openGames, setOpenGames = React.useState ([]: OpenGame list)
    let error, setError = React.useState (None: string option)
    let notice, setNotice = React.useState (None: string option)
    let busy, setBusy = React.useState (false)

    let loadOpen () =
        async {
            let! games = Api.api.ListOpenGames()
            setOpenGames games
        }
        |> Async.StartImmediate

    React.useEffect ((fun () -> loadOpen ()), [||])

    let optional value = if value = "" then None else Some value

    let handleCreate () =
        async {
            setBusy true
            setError None

            let! response =
                Api.api.CreateGame
                    { Nickname = nickname
                      Email = email
                      ColorChoice = color
                      OpponentNickname = optional opponentNick
                      OpponentEmail = optional opponentEmail
                      Message = optional message
                      Language = I18n.code props.Lang }

            setBusy false

            match response with
            | Ok credentials ->
                Api.saveCredentials credentials
                props.OnOpenGame credentials.GameId
            | Error message -> setError (Some message)
        }
        |> Async.StartImmediate

    let handleJoin (gameId: string) =
        async {
            setBusy true
            setError None

            let! response =
                Api.api.JoinGame
                    { GameId = gameId
                      Nickname = nickname
                      Email = email
                      Message = None
                      Language = I18n.code props.Lang }

            setBusy false

            match response with
            | Ok credentials ->
                Api.saveCredentials credentials
                props.OnOpenGame credentials.GameId
            | Error message -> setError (Some message)
        }
        |> Async.StartImmediate

    let handleDigest () =
        async {
            setBusy true
            setError None
            setNotice None

            let! response =
                Api.api.RequestDigest { Email = (if digestEmail = "" then email else digestEmail); Language = I18n.code props.Lang }

            setBusy false

            if response.Ok then setNotice (Some t.DigestSent)
            else setError response.Error
        }
        |> Async.StartImmediate

    let colorButton (label: string) (value: ColorChoice) =
        Html.button [
            prop.type'.button
            prop.className (
                "h-11 rounded border flex items-center justify-center gap-2 text-sm font-semibold transition-colors "
                + (match color with
                   | selected when selected = value -> "border-primary text-primary bg-surface-container"
                   | _ -> "border-outline-variant text-on-surface-variant hover:border-primary")
            )
            prop.onClick (fun _ -> setColor value)
            prop.text label
        ]

    Html.div [
        prop.className "w-full max-w-6xl mx-auto px-6 py-8 flex flex-col gap-8"
        prop.children [
            yield
                Html.div [
                    prop.children [
                        Html.div [ prop.className "text-xs uppercase tracking-widest text-secondary font-semibold"; prop.text t.Tagline ]
                        Html.h1 [ prop.className "text-3xl md:text-4xl font-extrabold tracking-tight text-primary"; prop.text t.NewGame ]
                    ]
                ]

            match error with
            | Some value ->
                yield
                    Html.div [
                        prop.className "rounded border border-error text-error px-4 py-3 text-sm"
                        prop.text value
                    ]
            | None -> ()

            match notice with
            | Some value ->
                yield
                    Html.div [
                        prop.className "rounded border border-secondary text-secondary px-4 py-3 text-sm"
                        prop.text value
                    ]
            | None -> ()

            yield
                Html.div [
                    prop.className "grid grid-cols-1 lg:grid-cols-3 gap-6 items-start"
                    prop.children [
                        Html.section [
                            prop.className "lg:col-span-2 bg-surface-container-low rounded-lg border border-outline-variant p-6 flex flex-col gap-4"
                            prop.children [
                                Html.h2 [ prop.className "text-lg font-bold text-on-surface"; prop.text t.NewGame ]
                                Html.div [
                                    prop.className "grid grid-cols-1 sm:grid-cols-2 gap-4"
                                    prop.children [
                                        field t.YourNickname nickname setNickname "Kasparov"
                                        field t.YourEmail email setEmail "name@example.com"
                                    ]
                                ]
                                Html.div [
                                    prop.className "flex flex-col gap-1"
                                    prop.children [
                                        Html.span [
                                            prop.className "text-xs uppercase tracking-wide text-on-surface-variant"
                                            prop.text t.YourColor
                                        ]
                                        Html.div [
                                            prop.className "grid grid-cols-3 gap-2"
                                            prop.children [
                                                colorButton t.White ColorChoice.AsWhite
                                                colorButton t.Random ColorChoice.RandomColor
                                                colorButton t.Black ColorChoice.AsBlack
                                            ]
                                        ]
                                    ]
                                ]
                                Html.div [
                                    prop.className "grid grid-cols-1 sm:grid-cols-2 gap-4"
                                    prop.children [
                                        field t.OpponentNickname opponentNick setOpponentNick t.OpponentOptional
                                        field t.OpponentEmail opponentEmail setOpponentEmail t.OpponentOptional
                                    ]
                                ]
                                Html.label [
                                    prop.className "flex flex-col gap-1"
                                    prop.children [
                                        Html.span [ prop.className "text-xs uppercase tracking-wide text-on-surface-variant"; prop.text t.Message ]
                                        Html.textarea [
                                            prop.className
                                                "p-3 rounded border border-outline-variant bg-surface-container-lowest text-on-surface focus:outline-none focus:border-primary resize-none"
                                            prop.rows 3
                                            prop.value message
                                            prop.onChange setMessage
                                        ]
                                    ]
                                ]
                                Html.button [
                                    prop.type'.button
                                    prop.className
                                        "h-12 rounded bg-primary text-on-primary font-bold tracking-wide uppercase hover:opacity-90 disabled:opacity-50"
                                    prop.disabled busy
                                    prop.onClick (fun _ -> handleCreate ())
                                    prop.text t.CreateGame
                                ]
                            ]
                        ]

                        Html.div [
                            prop.className "flex flex-col gap-6"
                            prop.children [
                                Html.section [
                                    prop.className "bg-surface-container-low rounded-lg border border-outline-variant p-6 flex flex-col gap-3"
                                    prop.children [
                                        Html.h2 [ prop.className "text-lg font-bold text-on-surface"; prop.text t.ResumeGame ]
                                        Html.input [
                                            prop.className
                                                "h-11 px-3 rounded border border-outline-variant bg-surface-container-lowest text-on-surface font-mono focus:outline-none focus:border-primary"
                                            prop.placeholder t.GameId
                                            prop.value resumeId
                                            prop.onChange setResumeId
                                        ]
                                        Html.button [
                                            prop.type'.button
                                            prop.className
                                                "h-11 rounded border border-outline-variant text-on-surface hover:border-primary"
                                            prop.onClick (fun _ -> if resumeId <> "" then props.OnOpenGame resumeId)
                                            prop.text t.ResumeGame
                                        ]
                                    ]
                                ]

                                Html.section [
                                    prop.className "bg-surface-container-low rounded-lg border border-outline-variant p-6 flex flex-col gap-3"
                                    prop.children [
                                        Html.h2 [ prop.className "text-lg font-bold text-on-surface"; prop.text t.RequestDigest ]
                                        Html.input [
                                            prop.className
                                                "h-11 px-3 rounded border border-outline-variant bg-surface-container-lowest text-on-surface focus:outline-none focus:border-primary"
                                            prop.placeholder t.YourEmail
                                            prop.value digestEmail
                                            prop.onChange setDigestEmail
                                        ]
                                        Html.button [
                                            prop.type'.button
                                            prop.className "h-11 rounded bg-primary text-on-primary font-semibold hover:opacity-90 disabled:opacity-50"
                                            prop.disabled busy
                                            prop.onClick (fun _ -> handleDigest ())
                                            prop.text t.RequestDigest
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]

            yield
                Html.section [
                    prop.className "flex flex-col gap-4"
                    prop.children [
                        yield Html.h2 [ prop.className "text-xl font-bold text-on-surface"; prop.text t.OpenTables ]

                        if List.isEmpty openGames then
                            yield Html.p [ prop.className "text-sm text-on-surface-variant"; prop.text t.NoOpenGames ]
                        else
                            yield
                                Html.div [
                                    prop.className "grid grid-cols-1 md:grid-cols-3 gap-4"
                                    prop.children [
                                        for game in openGames do
                                            yield
                                                Html.div [
                                                    prop.key game.GameId
                                                    prop.className
                                                        "bg-surface-container-low rounded-lg border border-outline-variant p-5 flex flex-col gap-3"
                                                    prop.children [
                                                        yield
                                                            Html.div [
                                                                prop.className "flex items-center justify-between text-xs text-on-surface-variant"
                                                                prop.children [
                                                                    Html.span [ prop.className "uppercase tracking-wide"; prop.text t.Waiting ]
                                                                    Html.span [ prop.className "font-mono"; prop.text game.GameId ]
                                                                ]
                                                            ]
                                                        yield Html.div [ prop.className "text-lg font-bold text-primary"; prop.text game.Creator ]
                                                        match game.Message with
                                                        | Some value ->
                                                            yield Html.p [ prop.className "text-sm text-on-surface-variant italic"; prop.text value ]
                                                        | None -> ()
                                                        yield
                                                            Html.button [
                                                                prop.type'.button
                                                                prop.className
                                                                    "h-10 rounded border border-primary text-primary hover:bg-primary hover:text-on-primary text-sm font-semibold"
                                                                prop.disabled busy
                                                                prop.onClick (fun _ -> handleJoin game.GameId)
                                                                prop.text t.Join
                                                            ]
                                                    ]
                                                ]
                                    ]
                                ]
                    ]
                ]
        ]
    ]
