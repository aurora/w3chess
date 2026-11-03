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
      DrawOfferSent: string
      FlipBoard: string
      CopyId: string
      Copied: string
      Result: string
      BackToLobby: string
      Loading: string
      Error: string
      GameNotFound: string
      OpponentMessage: string
      Promotion: string
      DigestSent: string
      ListInBrowser: string
      NoGames: string
      Open: string
      Material: string
      Login: string
      Logout: string
      CheckEmail: string
      Account: string
      YourGames: string
      LogInFirst: string
      ErrTimeout: string
      ErrNetwork: string
      ErrNotYourTurn: string
      ErrNotActive: string
      ErrIllegalMove: string
      ErrNotJoinable: string
      ErrNotParticipant: string
      ErrNoDrawOffer: string
      ErrLoginLink: string
      ErrEmailRequired: string
      ErrNicknameRequired: string
      RogueWorkerFound: string
      RogueWorkerRemove: string }

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
      DrawOfferSent = "You offered a draw and are waiting for your opponent's answer."
      FlipBoard = "Flip board"
      CopyId = "Copy game ID"
      Copied = "Copied!"
      Result = "Result"
      BackToLobby = "Back to lobby"
      Loading = "Loading…"
      Error = "Something went wrong"
      GameNotFound = "This game does not exist."
      OpponentMessage = "Opponent's message"
      Promotion = "Choose promotion"
      DigestSent = "Your games have been emailed to you."
      ListInBrowser = "List in browser"
      NoGames = "No games found for this address."
      Open = "Open"
      Material = "Material"
      Login = "Log in"
      Logout = "Log out"
      CheckEmail = "We sent you a login link by email."
      Account = "Account"
      YourGames = "Your games"
      LogInFirst = "Log in to see your account and your games."
      ErrTimeout = "The server did not respond in time. Please try again."
      ErrNetwork = "The connection to the server failed."
      ErrNotYourTurn = "It is not your turn."
      ErrNotActive = "The game is not active."
      ErrIllegalMove = "That move is not legal."
      ErrNotJoinable = "This game is not open for joining."
      ErrNotParticipant = "You are not a participant of this game."
      ErrNoDrawOffer = "There is no draw offer to accept."
      ErrLoginLink = "This login link is invalid, expired or has already been used."
      ErrEmailRequired = "Please enter a valid email address."
      ErrNicknameRequired = "Please enter a nickname."
      RogueWorkerFound =
        "A service worker from another application is registered for this address and can block requests to the game."
      RogueWorkerRemove = "Remove and reload" }

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
      DrawOfferSent = "Du hast Remis vorgeschlagen und wartest auf die Antwort deines Gegners."
      FlipBoard = "Brett drehen"
      CopyId = "Spiele-ID kopieren"
      Copied = "Kopiert!"
      Result = "Ergebnis"
      BackToLobby = "Zurück zur Lobby"
      Loading = "Lädt…"
      Error = "Etwas ist schiefgelaufen"
      GameNotFound = "Diese Partie existiert nicht."
      OpponentMessage = "Nachricht des Gegners"
      Promotion = "Umwandlung wählen"
      DigestSent = "Deine Partien wurden per E-Mail verschickt."
      ListInBrowser = "Im Browser auflisten"
      NoGames = "Keine Partien zu dieser Adresse gefunden."
      Open = "Öffnen"
      Material = "Material"
      Login = "Anmelden"
      Logout = "Abmelden"
      CheckEmail = "Wir haben dir einen Anmelde-Link per E-Mail geschickt."
      Account = "Konto"
      YourGames = "Deine Partien"
      LogInFirst = "Melde dich an, um dein Konto und deine Partien zu sehen."
      ErrTimeout = "Der Server hat nicht rechtzeitig geantwortet. Bitte versuche es erneut."
      ErrNetwork = "Die Verbindung zum Server ist fehlgeschlagen."
      ErrNotYourTurn = "Du bist nicht am Zug."
      ErrNotActive = "Die Partie ist nicht aktiv."
      ErrIllegalMove = "Dieser Zug ist nicht regelkonform."
      ErrNotJoinable = "Diese Partie ist nicht mehr offen zum Beitritt."
      ErrNotParticipant = "Du nimmst nicht an dieser Partie teil."
      ErrNoDrawOffer = "Es liegt kein Remis-Angebot zum Annehmen vor."
      ErrLoginLink = "Dieser Anmelde-Link ist ungültig, abgelaufen oder wurde bereits verwendet."
      ErrEmailRequired = "Bitte gib eine gültige E-Mail-Adresse ein."
      ErrNicknameRequired = "Bitte gib einen Spitznamen ein."
      RogueWorkerFound =
        "Für diese Adresse ist ein Service Worker einer anderen Anwendung registriert. Er kann Anfragen an das Spiel blockieren."
      RogueWorkerRemove = "Entfernen und neu laden" }

let ofLang lang = if lang = De then de else en

/// True for failures worth retrying automatically: the request never reached
/// the server or the answer did not arrive in time.
let isTransient (message: string) : bool =
    let lower = message.ToLowerInvariant()
    lower.Contains("timed out") || lower.Contains("abort") || lower.Contains("failed to fetch")

/// Maps a raw error message (server text, HTTP or network failure) to the UI
/// language. Unknown messages are shown unchanged.
let ofError (t: T) (message: string) : string =
    let contains (needle: string) =
        message.ToLowerInvariant().Contains(needle.ToLowerInvariant())

    if contains "timed out" || contains "abort" then t.ErrTimeout
    elif contains "failed to fetch" || contains "networkerror" then t.ErrNetwork
    elif contains "not your turn" then t.ErrNotYourTurn
    elif contains "game is not active" then t.ErrNotActive
    elif contains "illegal move" then t.ErrIllegalMove
    elif contains "game not found" then t.GameNotFound
    elif contains "not open for joining" then t.ErrNotJoinable
    elif contains "not a player in this game" || contains "invalid access token" then t.ErrNotParticipant
    elif contains "no draw offer" then t.ErrNoDrawOffer
    elif contains "already been used" || contains "has expired" then t.ErrLoginLink
    elif contains "valid email is required" then t.ErrEmailRequired
    elif contains "nickname is required" then t.ErrNicknameRequired
    else message

let ofCode (code: string) =
    if not (isNull code) && code.ToLowerInvariant().StartsWith("de") then De else En

let code = function
    | En -> "en"
    | De -> "de"
