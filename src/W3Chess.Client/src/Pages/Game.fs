module W3Chess.Client.Pages.Game

open Feliz
open W3Chess.Domain
open W3Chess.Contracts
open W3Chess.Client
open W3Chess.Client.Api
open W3Chess.Client.I18n
open W3Chess.Client.Components

type GameProps =
    { GameId: string
      Token: string option
      Lang: Lang
      OnBack: unit -> unit }

[<ReactComponent>]
let GamePage (props: GameProps) =
    let t = I18n.ofLang props.Lang
    let gameView, setGameView = React.useState (None: GameView option)
    let credentials, setCredentials = React.useState (None: PlayerCredentials option)
    let selected, setSelected = React.useState (None: Square option)
    let staged, setStaged = React.useState (None: (Square * Square * PieceKind option) option)
    let promotionPicker, setPromotionPicker = React.useState (None: (Square * Square) option)
    let message, setMessage = React.useState ("")
    let error, setError = React.useState (None: string option)
    let busy, setBusy = React.useState (false)
    let orientation, setOrientation = React.useState (Color.White)

    let reload () =
        async {
            try
                let! view = Api.getGame props.GameId
                setGameView view
            with ex ->
                setError (Some(sprintf "%s: %s" t.Error ex.Message))
        }

    React.useEffect (
        (fun () ->
            async {
                try
                    match Api.loadCredentials props.GameId with
                    | Some credentials -> setCredentials (Some credentials)
                    | None ->
                        match props.Token with
                        | Some token ->
                            let! validated = Api.validateToken props.GameId token

                            match validated with
                            | Some credentials ->
                                Api.saveCredentials credentials
                                setCredentials (Some credentials)
                            | None -> setError (Some t.Error)
                        | None -> ()

                    let! _ = reload ()
                    ()
                with ex ->
                    setError (Some(sprintf "%s: %s" t.Error ex.Message))
            }
            |> Async.StartImmediate),
        [| box props.GameId |]
    )

    let position = gameView |> Option.map (fun view -> Board.ofFen view.Fen)

    let legalMoves =
        match position with
        | Some position -> MoveGen.legalMoves position
        | None -> []

    let targets from =
        legalMoves |> List.filter (fun move -> move.From = from) |> List.map (fun move -> move.To) |> List.distinct

    let lastMove =
        gameView
        |> Option.bind (fun view ->
            match Array.tryLast view.Moves with
            | Some move -> Some(move.FromSquare, move.ToSquare)
            | None -> None)

    let submitMove (from: Square) (target: Square) (promotion: PieceKind option) =
        async {
            match credentials with
            | None -> setError (Some t.Error)
            | Some credentials ->
                try
                    setBusy true
                    setError None

                    let! response =
                        Api.submitMove
                            { GameId = props.GameId
                              PlayerId = credentials.PlayerId
                              Token = credentials.Token
                              FromSquare = from
                              ToSquare = target
                              Promotion = promotion
                              Message = (if message = "" then None else Some message) }

                    setBusy false

                    if response.Ok then
                        setStaged None
                        setSelected None
                        setMessage ""
                        let! _ = reload ()
                        ()
                    else
                        setError response.Error
                        setStaged None
                with ex ->
                    setBusy false
                    setError (Some(sprintf "%s: %s" t.Error ex.Message))
        }
        |> Async.StartImmediate

    let stage (from: Square) (target: Square) =
        let hasPromotion =
            legalMoves |> List.exists (fun move -> move.From = from && move.To = target && move.Promotion.IsSome)

        if hasPromotion then
            setPromotionPicker (Some(from, target))
        else
            setStaged (Some(from, target, None))

        setSelected None

    let onClick (square: Square) =
        match gameView, position with
        | Some view, Some position ->
            match selected with
            | None ->
                match position.Board.[square] with
                | Some piece when piece.Color = view.SideToMove -> setSelected (Some square)
                | _ -> ()
            | Some from ->
                if List.contains square (targets from) then
                    stage from square
                else
                    match position.Board.[square] with
                    | Some piece when piece.Color = view.SideToMove -> setSelected (Some square)
                    | _ -> setSelected None
        | _ -> ()

    let action (call: PlayerCredentials -> Async<ActionResponse>) =
        async {
            match credentials with
            | None -> setError (Some t.Error)
            | Some credentials ->
                try
                    setBusy true
                    setError None
                    let! response = call credentials
                    setBusy false

                    if response.Ok then
                        let! _ = reload ()
                        ()
                    else
                        setError response.Error
                with ex ->
                    setBusy false
                    setError (Some(sprintf "%s: %s" t.Error ex.Message))
        }
        |> Async.StartImmediate

    let statusText =
        match gameView with
        | None -> t.Loading
        | Some view ->
            match view.Status with
            | GameStatus.Waiting -> t.Waiting
            | GameStatus.Finished ->
                t.Finished + (match view.Result with Some result -> " · " + result | None -> "")
            | GameStatus.Active ->
                match credentials with
                | Some credentials -> if view.SideToMove = credentials.Color then t.YourTurn else t.OpponentTurn
                | None -> if view.SideToMove = White then t.White else t.Black

    let playerLabel (player: PlayerView option) fallback =
        match player with
        | Some value -> sprintf "%s (%s)" value.Nickname (if value.Color = White then t.White else t.Black)
        | None -> fallback

    let moveList (view: GameView) =
        if Array.isEmpty view.Moves then
            Html.p [ prop.className "text-sm text-on-surface-variant"; prop.text t.NoMoves ]
        else
            Html.div [
                prop.className "grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-sm"
                prop.children [
                    for move in view.Moves do
                        let prefix = if move.Ply % 2 = 1 then sprintf "%d." ((move.Ply + 1) / 2) else sprintf "%d…" ((move.Ply + 1) / 2)

                        yield
                            Html.div [
                                prop.key move.Ply
                                prop.className
                                    "rounded bg-surface-container px-3 py-2 flex flex-col border border-outline-variant"
                                prop.children [
                                    Html.span [
                                        prop.className "text-[10px] uppercase text-on-surface-variant"
                                        prop.text prefix
                                    ]
                                    Html.span [ prop.className "font-bold text-on-surface"; prop.text move.San ]
                                ]
                            ]
                ]
            ]

    Html.div [
        prop.className "w-full max-w-6xl mx-auto px-6 py-8 flex flex-col gap-6"
        prop.children [
            // Meta strip
            yield
                Html.div [
                    prop.className
                        "rounded-lg border border-outline-variant bg-surface-container-low px-5 py-4 flex flex-wrap items-center justify-between gap-4"
                    prop.children [
                        Html.div [
                            prop.className "flex flex-col gap-1"
                            prop.children [
                                Html.span [
                                    prop.className "text-xs uppercase tracking-widest text-on-surface-variant"
                                    prop.text (playerLabel (gameView |> Option.bind (fun v -> v.White)) t.White)
                                ]
                                Html.span [
                                    prop.className "text-xs uppercase tracking-widest text-on-surface-variant"
                                    prop.text (playerLabel (gameView |> Option.bind (fun v -> v.Black)) t.Black)
                                ]
                            ]
                        ]
                        Html.div [
                            prop.className "flex items-center gap-3"
                            prop.children [
                                Html.span [ prop.className "text-sm font-semibold text-primary"; prop.text statusText ]
                                Html.span [ prop.className "font-mono text-xs text-on-surface-variant"; prop.text props.GameId ]
                            ]
                        ]
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

            yield
                Html.div [
                    prop.className "grid grid-cols-1 lg:grid-cols-2 gap-6 items-start"
                    prop.children [
                        // Board
                        Html.div [
                            prop.className "flex flex-col gap-3"
                            prop.children [
                                match position with
                                | Some position ->
                                    yield
                                        Board.view
                                            { Position = position
                                              Orientation = orientation
                                              Selected = selected
                                              Targets = (match selected with Some from -> targets from | None -> [])
                                              LastMove = lastMove
                                              OnClick = onClick }
                                | None -> yield Html.div [ prop.className "aspect-square bg-surface-container-low rounded-lg" ]
                                yield
                                    Html.div [
                                        prop.className "flex items-center justify-between"
                                        prop.children [
                                            Html.button [
                                                prop.type'.button
                                                prop.className "text-sm text-on-surface-variant hover:text-primary"
                                                prop.onClick (fun _ ->
                                                    setOrientation (
                                                        if orientation = White then Color.Black else Color.White
                                                    ))
                                                prop.text t.FlipBoard
                                            ]
                                            Html.button [
                                                prop.type'.button
                                                prop.className "text-sm text-on-surface-variant hover:text-primary"
                                                prop.onClick (fun _ -> props.OnBack ())
                                                prop.text t.BackToLobby
                                            ]
                                        ]
                                    ]
                            ]
                        ]

                        // Control panel
                        Html.div [
                            prop.className "flex flex-col gap-4"
                            prop.children [
                                // Staged move / promotion / selected
                                yield
                                    Html.div [
                                        prop.className
                                            "rounded-lg border border-outline-variant bg-surface-container-low p-5 flex flex-col gap-3"
                                        prop.children [
                                            yield
                                                Html.span [
                                                    prop.className "text-xs uppercase tracking-wide text-on-surface-variant"
                                                    prop.text t.SelectedMove
                                                ]

                                            match promotionPicker with
                                            | Some(from, target) ->
                                                yield
                                                    Html.div [
                                                        prop.className "flex flex-col gap-2"
                                                        prop.children [
                                                            Html.span [ prop.className "text-sm"; prop.text t.Promotion ]
                                                            Html.div [
                                                                prop.className "grid grid-cols-4 gap-2"
                                                                prop.children [
                                                                    for kind in [ Queen; Rook; Bishop; Knight ] do
                                                                        yield
                                                                            Html.button [
                                                                                prop.key (string kind)
                                                                                prop.type'.button
                                                                                prop.className
                                                                                    "h-10 rounded border border-outline-variant hover:border-primary text-on-surface font-mono"
                                                                                prop.onClick (fun _ ->
                                                                                    setStaged (Some(from, target, Some kind))
                                                                                    setPromotionPicker None)
                                                                                prop.text (string (Notation.pieceLetter kind))
                                                                            ]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                            | None ->
                                                match staged with
                                                | Some(from, target, _) ->
                                                    yield
                                                        Html.div [
                                                            prop.className "font-mono text-2xl font-black text-primary"
                                                            prop.text (
                                                                sprintf "%s – %s" (Square.toAlgebraic from) (Square.toAlgebraic target)
                                                            )
                                                        ]
                                                | None ->
                                                    yield
                                                        Html.span [
                                                            prop.className "text-sm text-on-surface-variant italic"
                                                            prop.text "—"
                                                        ]
                                        ]
                                    ]

                                // Message
                                yield
                                    Html.div [
                                        prop.className
                                            "rounded-lg border border-outline-variant bg-surface-container-low p-5 flex flex-col gap-3"
                                        prop.children [
                                            Html.span [
                                                prop.className "text-xs uppercase tracking-wide text-on-surface-variant"
                                                prop.text t.MessageToOpponent
                                            ]
                                            Html.textarea [
                                                prop.className
                                                    "p-3 rounded border border-outline-variant bg-surface-container-lowest text-on-surface focus:outline-none focus:border-primary resize-none"
                                                prop.rows 2
                                                prop.value message
                                                prop.onChange setMessage
                                            ]
                                            Html.button [
                                                prop.type'.button
                                                prop.className
                                                    "h-12 rounded bg-primary text-on-primary font-bold disabled:opacity-50"
                                                prop.disabled (busy || staged.IsNone || credentials.IsNone)
                                                prop.onClick (fun _ ->
                                                    match staged with
                                                    | Some(from, target, promotion) -> submitMove from target promotion
                                                    | None -> ())
                                                prop.text t.ConfirmMove
                                            ]
                                        ]
                                    ]

                                // Actions
                                yield
                                    Html.div [
                                        prop.className "grid grid-cols-2 gap-3"
                                        prop.children [
                                            Html.button [
                                                prop.type'.button
                                                prop.className "h-11 rounded border border-outline-variant hover:border-primary text-sm"
                                                prop.disabled (busy || credentials.IsNone)
                                                prop.onClick (fun _ ->
                                                    action (fun credentials ->
                                                        Api.offerDraw
                                                            { GameId = props.GameId
                                                              PlayerId = credentials.PlayerId
                                                              Token = credentials.Token
                                                              Message = None }))
                                                prop.text t.OfferDraw
                                            ]
                                            Html.button [
                                                prop.type'.button
                                                prop.className "h-11 rounded border border-outline-variant hover:border-error text-sm text-error"
                                                prop.disabled (busy || credentials.IsNone)
                                                prop.onClick (fun _ ->
                                                    action (fun credentials ->
                                                        Api.resign
                                                            { GameId = props.GameId
                                                              PlayerId = credentials.PlayerId
                                                              Token = credentials.Token
                                                              Message = None }))
                                                prop.text t.Resign
                                            ]
                                        ]
                                    ]

                                match gameView with
                                | Some view when view.DrawOfferPending ->
                                    yield
                                        Html.div [
                                            prop.className
                                                "rounded border border-secondary text-secondary p-4 flex flex-col gap-3"
                                            prop.children [
                                                Html.span [ prop.className "text-sm"; prop.text t.DrawOffered ]
                                                Html.button [
                                                    prop.type'.button
                                                    prop.className
                                                        "h-11 rounded bg-secondary text-on-secondary font-semibold disabled:opacity-50"
                                                    prop.disabled (busy || credentials.IsNone)
                                                    prop.onClick (fun _ ->
                                                        action (fun credentials ->
                                                            Api.acceptDraw
                                                                { GameId = props.GameId
                                                                  PlayerId = credentials.PlayerId
                                                                  Token = credentials.Token
                                                                  Message = None }))
                                                    prop.text t.AcceptDraw
                                                ]
                                            ]
                                        ]
                                | _ -> ()

                                // Moves
                                yield
                                    Html.div [
                                        prop.className
                                            "rounded-lg border border-outline-variant bg-surface-container-low p-5 flex flex-col gap-3"
                                        prop.children [
                                            yield
                                                Html.span [
                                                    prop.className "text-xs uppercase tracking-wide text-on-surface-variant"
                                                    prop.text t.Moves
                                                ]
                                            match gameView with
                                            | Some view -> yield moveList view
                                            | None -> yield Html.p [ prop.text t.Loading ]
                                        ]
                                    ]

                                match gameView |> Option.bind (fun v -> v.Message) with
                                | Some value ->
                                    yield
                                        Html.div [
                                            prop.className
                                                "rounded-lg border border-outline-variant bg-surface-container-low p-5 flex flex-col gap-1"
                                            prop.children [
                                                Html.span [
                                                    prop.className "text-xs uppercase tracking-wide text-on-surface-variant"
                                                    prop.text t.OpponentMessage
                                                ]
                                                Html.p [ prop.className "italic text-on-surface"; prop.text value ]
                                            ]
                                        ]
                                | None -> ()
                            ]
                        ]
                    ]
                ]
        ]
    ]
