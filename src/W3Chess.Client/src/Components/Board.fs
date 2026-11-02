module W3Chess.Client.Components.Board

open Feliz
open W3Chess.Domain

type Props =
    { Position: Position
      Orientation: Color
      Selected: Square option
      Targets: Square list
      LastMove: (Square * Square) option
      OnClick: Square -> unit }

let private glyph kind =
    match kind with
    | Pawn -> "\u265F"
    | Knight -> "\u265E"
    | Bishop -> "\u265D"
    | Rook -> "\u265C"
    | Queen -> "\u265B"
    | King -> "\u265A"

let view (props: Props) =
    let ranks = if props.Orientation = White then [ 7..-1..0 ] else [ 0..7 ]
    let files = if props.Orientation = White then [ 0..7 ] else [ 7..-1..0 ]

    Html.div [
        prop.className
            "grid grid-cols-8 grid-rows-[repeat(8,minmax(0,1fr))] aspect-square w-full rounded-md overflow-hidden select-none shadow-xl [container-type:inline-size]"
        prop.children [
            for rank in ranks do
                for file in files do
                    let square = Square.create file rank
                    let piece = props.Position.Board.[square]
                    let isDark = (file + rank) % 2 = 0
                    let isSelected = props.Selected = Some square
                    let isTarget = List.contains square props.Targets

                    let isLast =
                        match props.LastMove with
                        | Some(from, target) -> square = from || square = target
                        | None -> false

                    yield
                        Html.div [
                            prop.key (string square)
                            prop.className (
                                "relative flex items-center justify-center cursor-pointer leading-none min-h-0 min-w-0 overflow-hidden "
                                + (if isDark then "bg-board-dark" else "bg-board-light")
                            )
                            prop.onClick (fun _ -> props.OnClick square)
                            prop.children [
                                if isLast then
                                    yield Html.div [ prop.className "absolute inset-0 bg-primary/20" ]

                                match piece with
                                | Some value ->
                                    yield
                                        Html.span [
                                            prop.className (
                                                "relative z-10 leading-none text-[9cqw] "
                                                + (if value.Color = White then "piece-white" else "piece-black")
                                            )
                                            prop.text (glyph value.Kind)
                                        ]
                                | None -> ()

                                if isSelected then
                                    yield Html.div [ prop.className "absolute inset-0 border-2 border-primary" ]

                                if isTarget then
                                    yield
                                        Html.div [
                                            prop.className "absolute w-1/3 h-1/3 rounded-full bg-primary/40"
                                        ]
                            ]
                        ]
        ]
    ]

let squareLabel (square: Square) = Square.toAlgebraic square
