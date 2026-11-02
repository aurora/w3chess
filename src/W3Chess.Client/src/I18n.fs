module W3Chess.Client.I18n

type Lang =
    | En
    | De

type T =
    { AppName: string
      Tagline: string
      Lobby: string
      NewGame: string
      YourNickname: string
      YourEmail: string
      YourColor: string
      White: string
      Black: string
      Random: string
      OpponentOptional: string
      OpponentNickname: string
      OpponentEmail: string
      Message: string
      CreateGame: string
      ResumeGame: string
      GameId: string
      RequestDigest: string
      OpenTables: string
      NoOpenGames: string
      Join: string
      DarkMode: string
      LightMode: string
      Language: string
      Active: string
      Waiting: string
      Finished: string
      YourTurn: string
      OpponentTurn: string
      Moves: string
      NoMoves: string
      SelectedMove: string
      MessageToOpponent: string
      ConfirmMove: string
      Resign: string
      OfferDraw: string
      AcceptDraw: string
      DrawOffered: string
      FlipBoard: string
      CopyId: string
      Copied: string
      Result: string
      BackToLobby: string
      Loading: string
      Error: string
      OpponentMessage: string
      Promotion: string
      DigestSent: string }

let private en: T =
    { AppName = "W3Chess"
      Tagline = "Correspondence chess"
      Lobby = "Lobby"
      NewGame = "New game"
      YourNickname = "Your nickname"
      YourEmail = "Your email"
      YourColor = "Your colour"
      White = "White"
      Black = "Black"
      Random = "Random"
      OpponentOptional = "Opponent (optional)"
      OpponentNickname = "Opponent nickname"
      OpponentEmail = "Opponent email"
      Message = "Message"
      CreateGame = "Create game & send invitation"
      ResumeGame = "Open a game by ID"
      GameId = "Game ID"
      RequestDigest = "Email me my games"
      OpenTables = "Open tables"
      NoOpenGames = "There are no open invitations right now."
      Join = "Accept challenge"
      DarkMode = "Dark"
      LightMode = "Light"
      Language = "Language"
      Active = "Active"
      Waiting = "Waiting for an opponent"
      Finished = "Finished"
      YourTurn = "It is your turn"
      OpponentTurn = "Waiting for the opponent"
      Moves = "Moves"
      NoMoves = "No moves yet."
      SelectedMove = "Selected move"
      MessageToOpponent = "Message to your opponent"
      ConfirmMove = "Confirm move & send email"
      Resign = "Resign"
      OfferDraw = "Offer draw"
      AcceptDraw = "Accept draw"
      DrawOffered = "A draw has been offered."
      FlipBoard = "Flip board"
      CopyId = "Copy game ID"
      Copied = "Copied!"
      Result = "Result"
      BackToLobby = "Back to lobby"
      Loading = "Loading…"
      Error = "Something went wrong"
      OpponentMessage = "Opponent's message"
      Promotion = "Choose promotion"
      DigestSent = "Your games have been emailed to you." }

let private de: T =
    { AppName = "W3Chess"
      Tagline = "Fernschach"
      Lobby = "Lobby"
      NewGame = "Neue Partie"
      YourNickname = "Dein Spitzname"
      YourEmail = "Deine E-Mail-Adresse"
      YourColor = "Deine Farbwahl"
      White = "Weiß"
      Black = "Schwarz"
      Random = "Zufall"
      OpponentOptional = "Gegner (optional)"
      OpponentNickname = "Gegner-Spitzname"
      OpponentEmail = "Gegner-E-Mail"
      Message = "Nachricht"
      CreateGame = "Partie erstellen & Einladung senden"
      ResumeGame = "Partie per ID öffnen"
      GameId = "Spiele-ID"
      RequestDigest = "Partien per E-Mail senden"
      OpenTables = "Offene Tische"
      NoOpenGames = "Derzeit gibt es keine offenen Einladungen."
      Join = "Herausforderung annehmen"
      DarkMode = "Dunkel"
      LightMode = "Hell"
      Language = "Sprache"
      Active = "Aktiv"
      Waiting = "Wartet auf Gegner"
      Finished = "Beendet"
      YourTurn = "Du bist am Zug"
      OpponentTurn = "Warte auf den Gegner"
      Moves = "Züge"
      NoMoves = "Noch keine Züge."
      SelectedMove = "Ausgewählter Zug"
      MessageToOpponent = "Nachricht an den Gegner"
      ConfirmMove = "Zug bestätigen & E-Mail senden"
      Resign = "Partie aufgeben"
      OfferDraw = "Remis vorschlagen"
      AcceptDraw = "Remis annehmen"
      DrawOffered = "Es liegt ein Remis-Angebot vor."
      FlipBoard = "Brett drehen"
      CopyId = "Spiele-ID kopieren"
      Copied = "Kopiert!"
      Result = "Ergebnis"
      BackToLobby = "Zurück zur Lobby"
      Loading = "Lädt…"
      Error = "Etwas ist schiefgelaufen"
      OpponentMessage = "Nachricht des Gegners"
      Promotion = "Umwandlung wählen"
      DigestSent = "Deine Partien wurden per E-Mail verschickt." }

let ofLang lang = if lang = De then de else en

let ofCode (code: string) =
    if not (isNull code) && code.ToLowerInvariant().StartsWith("de") then De else En

let code = function
    | En -> "en"
    | De -> "de"
