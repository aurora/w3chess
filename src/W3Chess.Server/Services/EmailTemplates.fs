namespace W3Chess.Server.Services

open W3Chess.Domain

/// A rendered plain-text email.
type Mail = { To: string; Subject: string; Body: string }

/// Localised plain-text email rendering. Every user-facing string exists in
/// English and German; missing language codes fall back to English.
module EmailTemplates =
    let private isGerman (lang: string) =
        not (isNull lang) && lang.ToLowerInvariant().StartsWith("de")

    let private t lang de en = if isGerman lang then de else en

    let link (baseUrl: string) (gameId: string) (token: string) =
        sprintf "%s/#/game/%s?token=%s" (baseUrl.TrimEnd('/')) gameId token

    let login lang (url: string) =
        let subject = t lang "Dein Anmelde-Link" "Your login link"

        let body =
            String.concat
                "\n"
                [ t lang "Hallo," "Hello,"
                  ""
                  t
                      lang
                      "Klicke auf den Link, um dich anzumelden. Er ist 30 Minuten gültig und kann nur einmal verwendet werden."
                      "Click the link to log in. It is valid for 30 minutes and can be used once."
                  ""
                  url ]

        subject, body

    /// Appended to game mails for account holders: a login link that lands
    /// back on the game.
    let loginLink lang (url: string) =
        t
            lang
            (sprintf
                "Mit diesem Link meldest du dich bei W3Chess an und kommst direkt zur Partie zurück (30 Minuten gültig):\n%s"
                url)
            (sprintf
                "This link logs you in to W3Chess and takes you straight back to the game (valid for 30 minutes):\n%s"
                url)

    /// Renders the legacy 64-character board (rank 8 first) as ASCII art.
    let renderBoard (legacyBoard: string) (flip: bool) =
        let cellAt file rank = legacyBoard.[(7 - rank) * 8 + file]
        let ranks = if flip then [ 0..7 ] else [ 7..-1..0 ]
        let files = if flip then [ 7..-1..0 ] else [ 0..7 ]
        let builder = System.Text.StringBuilder()

        builder.AppendLine("  +-----------------+") |> ignore

        for rank in ranks do
            builder.Append(sprintf "%d | " (rank + 1)) |> ignore

            for file in files do
                let piece = cellAt file rank
                builder.Append(if piece = 'e' then ". " else string piece + " ") |> ignore

            builder.AppendLine("|") |> ignore

        builder.AppendLine("  +-----------------+") |> ignore
        builder.Append("    ") |> ignore

        for file in files do
            builder.Append(string (char (int 'a' + file)) + " ") |> ignore

        builder.ToString()

    let private intro lang gameId =
        t lang (sprintf "Partie-ID: %s" gameId) (sprintf "Game ID: %s" gameId)

    let private linkBlock lang url =
        t
            lang
            (sprintf "Weiterspielen:\n%s" url)
            (sprintf "Continue the game:\n%s" url)

    let invitation lang baseUrl gameId token creatorNick opponentNick board =
        let subject =
            t lang
                (sprintf "%s lädt dich zu einer Partie ein" creatorNick)
                (sprintf "%s invites you to a game" creatorNick)

        let body =
            String.concat
                "\n"
                [ t lang (sprintf "Hallo %s," opponentNick) (sprintf "Hello %s," opponentNick)
                  ""
                  t
                      lang
                      (sprintf "%s hat dich zu einer Fernschachpartie herausgefordert." creatorNick)
                      (sprintf "%s has challenged you to a correspondence game." creatorNick)
                  ""
                  intro lang gameId
                  ""
                  renderBoard board false
                  ""
                  linkBlock lang (link baseUrl gameId token) ]

        subject, body

    let confirmation lang baseUrl gameId token board =
        let subject = t lang "Deine Partie wurde erstellt" "Your game has been created"

        let body =
            String.concat
                "\n"
                [ t lang "Hallo," "Hello,"
                  ""
                  t lang "Deine Partie ist bereit." "Your game is ready."
                  ""
                  intro lang gameId
                  ""
                  renderBoard board false
                  ""
                  linkBlock lang (link baseUrl gameId token) ]

        subject, body

    let joined lang baseUrl gameId token opponentNick board =
        let subject =
            t lang
                (sprintf "%s ist deiner Partie beigetreten" opponentNick)
                (sprintf "%s joined your game" opponentNick)

        let body =
            String.concat
                "\n"
                [ t lang "Hallo," "Hello,"
                  ""
                  t
                      lang
                      (sprintf "%s hat deine offene Einladung angenommen. Die Partie beginnt." opponentNick)
                      (sprintf "%s accepted your open invitation. The game begins." opponentNick)
                  ""
                  intro lang gameId
                  ""
                  renderBoard board false
                  ""
                  linkBlock lang (link baseUrl gameId token) ]

        subject, body

    let yourTurn lang baseUrl gameId token opponentNick lastMove board =
        let subject =
            t lang
                (sprintf "Du bist am Zug (%s)" gameId)
                (sprintf "It is your turn (%s)" gameId)

        let body =
            String.concat
                "\n"
                [ t lang (sprintf "Hallo %s," opponentNick) (sprintf "Hello %s," opponentNick)
                  ""
                  t lang (sprintf "Der letzte Zug war %s." lastMove) (sprintf "The last move was %s." lastMove)
                  ""
                  intro lang gameId
                  ""
                  renderBoard board false
                  ""
                  linkBlock lang (link baseUrl gameId token) ]

        subject, body

    let drawOffer lang baseUrl gameId token opponentNick board =
        let subject =
            t lang
                (sprintf "Remisangebot (%s)" gameId)
                (sprintf "Draw offer (%s)" gameId)

        let body =
            String.concat
                "\n"
                [ t lang (sprintf "Hallo %s," opponentNick) (sprintf "Hello %s," opponentNick)
                  ""
                  t
                      lang
                      "Dein Gegner bietet Remis an. Nimm das Angebot auf der Partieseite an."
                      "Your opponent has offered a draw. Accept the offer on the game page."
                  ""
                  intro lang gameId
                  ""
                  renderBoard board false
                  ""
                  linkBlock lang (link baseUrl gameId token) ]

        subject, body

    let moveCopy lang baseUrl gameId token lastMove board =
        let subject = t lang (sprintf "Zug gesendet (%s)" gameId) (sprintf "Move sent (%s)" gameId)

        let body =
            String.concat
                "\n"
                [ t lang "Hallo," "Hello,"
                  ""
                  t lang (sprintf "Du hast %s gespielt." lastMove) (sprintf "You played %s." lastMove)
                  ""
                  intro lang gameId
                  ""
                  renderBoard board false
                  ""
                  linkBlock lang (link baseUrl gameId token) ]

        subject, body

    let finished lang baseUrl gameId token resultText =
        let subject = t lang (sprintf "Partie beendet (%s)" gameId) (sprintf "Game finished (%s)" gameId)

        let body =
            String.concat
                "\n"
                [ t lang "Hallo," "Hello,"
                  ""
                  t lang (sprintf "Die Partie ist beendet: %s" resultText) (sprintf "The game is finished: %s" resultText)
                  ""
                  intro lang gameId
                  ""
                  linkBlock lang (link baseUrl gameId token) ]

        subject, body

    let digest lang entries =
        let subject = t lang "Deine Partien" "Your games"

        let body =
            String.concat
                "\n"
                (t lang "Hallo," "Hello,"
                 :: ""
                 :: t lang "Hier ist die Liste deiner Partien:" "Here is the list of your games:"
                 :: ""
                 :: entries)

        subject, body
