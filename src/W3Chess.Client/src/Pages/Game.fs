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

let private pieceValue =
    function
    | Pawn -> 1
    | Knight -> 3
    | Bishop -> 3
    | Rook -> 5
    | Queen -> 9
    | King -> 0

let private initialCount =
    function
    | Pawn -> 8
    | Knight
    | Bishop
    | Rook -> 2
    | Queen
    | King -> 1

/// Missing pieces per colour: (captured by White = black pieces gone,
/// captured by Black = white pieces gone). Promotions are ignored.
let private capturedPieces (position: Position) =
    let present color =
        position.Board
        |> Array.choose id
        |> Array.filter (fun piece -> piece.Color = color)
        |> Array.countBy (fun piece -> piece.Kind)
        |> Map.ofArray

    let missing color =
        [ Pawn; Knight; Bishop; Rook; Queen; King ]
        |> List.collect (fun kind ->
            let count = Map.tryFind kind (present color) |> Option.defaultValue 0
            List.replicate (max 0 (initialCount kind - count)) kind)

    missing Color.Black, missing Color.White

/// Positive when White is ahead in material.
let private materialScore (position: Position) =
    position.Board
    |> Array.choose id
    |> Array.sumBy (fun piece -> if piece.Color = White then pieceValue piece.Kind else -pieceValue piece.Kind)

[<ReactComponent>]
let GamePage (props: GameProps) =
    let t = I18n.ofLang props.Lang
    let gameView, setGameView = React.useState (None: GameView option)
    let credentials, setCredentials = React.useState (None: PlayerCredentials option)
    let session = Api.currentSession ()
    let selected, setSelected = React.useState (None: Square option)
    let staged, setStaged = React.useState (None: (Square * Square * PieceKind option) option)
    let promotionPicker, setPromotionPicker = React.useState (None: (Square * Square) option)
    let message, setMessage = React.useState ("")
    let error, setError = React.useState (None: string option)
    let busy, setBusy = React.useState (false)
    let orientation, setOrientation = React.useState (Color.White)
    let offeredByMe, setOfferedByMe = React.useState (false)

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

    let subscriptionToken =
        credentials
        |> Option.map (fun value -> value.Token)
        |> Option.orElse props.Token

    let subscriptionKey = sprintf "%s|%s" (defaultArg subscriptionToken "") (defaultArg session "")

    React.useEffect (
        (fun () ->
            Api.subscribeGame
                props.GameId
                subscriptionToken
                session
                (fun () -> Async.StartImmediate (reload ()))),
        [| box props.GameId; box subscriptionKey |]
    )

    // Forget a locally-flagged offer of this browser once none is pending.
    React.useEffect (
        (fun () ->
            match gameView with
            | Some view when not view.DrawOfferPending -> setOfferedByMe false
            | _ -> ()),
        [| box gameView |]
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

    /// The pending offer was made from this browser or by this client's player.
    let drawOfferIsMine =
        match gameView with
        | Some view ->
            view.DrawOfferPending
            && (offeredByMe || view.DrawOfferBy = (credentials |> Option.map (fun c -> c.PlayerId)))
        | None -> false

    let submitMove (from: Square) (target: Square) (promotion: PieceKind option) =
        async {
            match credentials, session with
            | None, None -> setError (Some t.Error)
            | _ ->
                try
                    setBusy true
                    setError None
                    let playerId = credentials |> Option.map (fun c -> c.PlayerId) |> Option.defaultValue ""
                    let token = credentials |> Option.map (fun c -> c.Token) |> Option.defaultValue ""

                    let! response =
                        Api.submitMove
                            { GameId = props.GameId
                              PlayerId = playerId
                              Token = token
                              FromSquare = from
                              ToSquare = target
                              Promotion = promotion
                              Message = (if message = "" then None else Some message)
                              Session = session }

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

    let onDragStart (square: Square) =
        match position with
        | Some position ->
            match position.Board.[square] with
            | Some piece when piece.Color = position.SideToMove -> setSelected (Some square)
            | _ -> ()
        | None -> ()

    let onDrop (source: Square) (target: Square) =
        if source <> target && List.contains target (targets source) then
            stage source target
        else
            setSelected None

    let action (call: PlayerCredentials -> Async<ActionResponse>) =
        async {
            match credentials, session with
            | None, None -> setError (Some t.Error)
            | _ ->
                try
                    setBusy true
                    setError None
                    let creds =
                        credentials
                        |> Option.defaultValue
                            { GameId = props.GameId
                              PlayerId = ""
                              Token = ""
                              Color = White }
                    let! response = call creds
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

    let glyphRow (color: Color) (kinds: PieceKind list) =
        kinds
        |> List.map (fun kind ->
            Html.span [
                prop.className ((if color = White then "piece-white" else "piece-black") + " text-base leading-none")
                prop.text (string (Board.glyph kind))
            ])

    let materialBar (position: Position) =
        let capturedByWhite, capturedByBlack = capturedPieces position
        let score = materialScore position

        let scoreText =
            if score > 0 then sprintf "+%d" score
            elif score < 0 then string score
            else "0"

        Html.div [
            prop.className
                "flex items-center justify-between gap-4 rounded bg-surface-container px-3 py-2 text-xs text-on-surface-variant"
            prop.children [
                Html.div [
                    prop.className "flex items-center gap-1"
                    prop.children [
                        Html.span [ prop.className "uppercase tracking-wide"; prop.text (t.White + ":") ]
                        Html.span [
                            prop.className "flex flex-wrap items-center gap-0.5"
                            prop.children (glyphRow Black capturedByWhite)
                        ]
                    ]
                ]
                Html.span [
                    prop.className "font-mono font-bold text-primary"
                    prop.text (sprintf "%s %s" t.Material scoreText)
                ]
                Html.div [
                    prop.className "flex items-center gap-1"
                    prop.children [
                        Html.span [ prop.className "uppercase tracking-wide"; prop.text (t.Black + ":") ]
                        Html.span [
                            prop.className "flex flex-wrap items-center gap-0.5"
                            prop.children (glyphRow White capturedByBlack)
                        ]
                    ]
                ]
            ]
        ]

    let playerLabel (player: PlayerView option) fallback =
        match player with
        | Some value -> sprintf "%s (%s)" value.Nickname (if value.Color = White then t.White else t.Black)
        | None -> fallback

    let moveList (view: GameView) =
        if Array.isEmpty view.Moves then
            Html.p [ prop.className "text-sm text-on-surface-variant"; prop.text t.NoMoves ]
        else
            let lastPly = view.Moves.[view.Moves.Length - 1].Ply

            let cell (move: MoveView option) =
                match move with
                | Some value ->
                    Html.span [
                        prop.className (
                            "px-1.5 py-0.5 rounded "
                            + (if value.Ply = lastPly then
                                   "bg-primary/20 text-primary font-bold"
                               else
                                   "text-on-surface")
                        )
                        prop.text value.San
                    ]
                | None -> Html.span [ prop.className "text-on-surface-variant"; prop.text "–" ]

            Html.div [
                prop.className "flex flex-col font-mono text-sm max-h-64 overflow-y-auto"
                prop.children [
                    yield
                        Html.div [
                            prop.className
                                "grid grid-cols-[2.5rem_1fr_1fr] gap-2 px-2 pb-1 text-[10px] uppercase tracking-wide text-on-surface-variant border-b border-outline-variant"
                            prop.children [
                                Html.span [ prop.text "#" ]
                                Html.span [ prop.text t.White ]
                                Html.span [ prop.text t.Black ]
                            ]
                        ]

                    for row in 0 .. (view.Moves.Length - 1) / 2 do
                        let white = Array.tryItem (row * 2) view.Moves
                        let black = Array.tryItem (row * 2 + 1) view.Moves

                        yield
                            Html.div [
                                prop.key row
                                prop.className "grid grid-cols-[2.5rem_1fr_1fr] gap-2 px-2 py-0.5"
                                prop.children [
                                    Html.span [
                                        prop.className "text-on-surface-variant"
                                        prop.text (sprintf "%d." (row + 1))
                                    ]
                                    cell white
                                    cell black
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
                                    yield materialBar position
                                    yield
                                        Html.div [
                                            prop.className "relative"
                                            prop.children [
                                                yield
                                                    Board.view
                                                        { Position = position
                                                          Orientation = orientation
                                                          Selected = selected
                                                          Targets = (match selected with Some from -> targets from | None -> [])
                                                          LastMove = lastMove
                                                          OnClick = onClick
                                                          OnDragStart = onDragStart
                                                          OnDrop = onDrop }

                                                match promotionPicker with
                                                | Some(from, target) ->
                                                    yield
                                                        Html.div [
                                                            prop.className
                                                                "absolute inset-0 z-30 flex items-center justify-center bg-black/50 backdrop-blur-sm"
                                                            prop.children [
                                                                Html.div [
                                                                    prop.className
                                                                        "rounded-lg border border-outline-variant bg-surface-container-low p-3 flex flex-col gap-2 items-center shadow-xl"
                                                                    prop.children [
                                                                        Html.span [
                                                                            prop.className
                                                                                "text-xs uppercase tracking-wide text-on-surface-variant"
                                                                            prop.text t.Promotion
                                                                        ]
                                                                        Html.div [
                                                                            prop.className "flex gap-2"
                                                                            prop.children [
                                                                                for kind in [ Queen; Rook; Bishop; Knight ] do
                                                                                    yield
                                                                                        Html.button [
                                                                                            prop.key (string kind)
                                                                                            prop.type'.button
                                                                                            prop.className
                                                                                                "w-12 h-12 rounded border border-outline-variant text-3xl text-on-surface hover:border-primary"
                                                                                            prop.onClick (fun _ ->
                                                                                                setStaged (Some(from, target, Some kind))
                                                                                                setPromotionPicker None)
                                                                                            prop.text (string (Board.glyph kind))
                                                                                        ]
                                                                            ]
                                                                        ]
                                                                    ]
                                                                ]
                                                            ]
                                                        ]
                                                | None -> ()
                                            ]
                                        ]
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
                                                prop.disabled (busy || staged.IsNone || (credentials.IsNone && session.IsNone))
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
                                                prop.disabled (busy || (credentials.IsNone && session.IsNone))
                                                prop.onClick (fun _ ->
                                                    setOfferedByMe true

                                                    action (fun credentials ->
                                                        Api.offerDraw
                                                            { GameId = props.GameId
                                                              PlayerId = credentials.PlayerId
                                                              Token = credentials.Token
                                                              Message = None; Session = session }))
                                                prop.text t.OfferDraw
                                            ]
                                            Html.button [
                                                prop.type'.button
                                                prop.className "h-11 rounded border border-outline-variant hover:border-error text-sm text-error"
                                                prop.disabled (busy || (credentials.IsNone && session.IsNone))
                                                prop.onClick (fun _ ->
                                                    action (fun credentials ->
                                                        Api.resign
                                                            { GameId = props.GameId
                                                              PlayerId = credentials.PlayerId
                                                              Token = credentials.Token
                                                              Message = None; Session = session }))
                                                prop.text t.Resign
                                            ]
                                        ]
                                    ]

                                match gameView with
                                | Some view when view.DrawOfferPending ->
                                    if drawOfferIsMine then
                                        yield
                                            Html.div [
                                                prop.className "rounded border border-secondary text-secondary p-4 text-sm"
                                                prop.text t.DrawOfferSent
                                            ]
                                    else
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
                                                        prop.disabled (busy || (credentials.IsNone && session.IsNone))
                                                        prop.onClick (fun _ ->
                                                            action (fun credentials ->
                                                                Api.acceptDraw
                                                                    { GameId = props.GameId
                                                                      PlayerId = credentials.PlayerId
                                                                      Token = credentials.Token
                                                                      Message = None; Session = session }))
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
