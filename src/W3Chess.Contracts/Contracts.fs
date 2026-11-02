namespace W3Chess.Contracts

open W3Chess.Domain

/// Colour chosen when creating a game.
type ColorChoice =
    | AsWhite
    | AsBlack
    | RandomColor

/// Lifecycle of a game as seen by the client.
type GameStatus =
    | Waiting
    | Active
    | Finished

type CreateGameRequest =
    { Nickname: string
      Email: string
      ColorChoice: ColorChoice
      OpponentNickname: string option
      OpponentEmail: string option
      Message: string option
      Language: string }

type PlayerCredentials =
    { GameId: string
      PlayerId: string
      Token: string
      Color: Color }

type JoinGameRequest =
    { GameId: string
      Nickname: string
      Email: string
      Message: string option
      Language: string }

type PlayerView = { Nickname: string; Color: Color }

type MoveView =
    { Ply: int
      San: string
      Uci: string
      FromSquare: Square
      ToSquare: Square
      Message: string option }

type GameView =
    { GameId: string
      White: PlayerView option
      Black: PlayerView option
      SideToMove: Color
      Status: GameStatus
      Result: string option
      Fen: string
      Moves: MoveView[]
      Message: string option
      DrawOfferPending: bool }

type OpenGame =
    { GameId: string
      Creator: string
      CreatedAt: string
      Message: string option }

type SubmitMoveRequest =
    { GameId: string
      PlayerId: string
      Token: string
      FromSquare: Square
      ToSquare: Square
      Promotion: PieceKind option
      Message: string option }

type ActionRequest =
    { GameId: string
      PlayerId: string
      Token: string
      Message: string option }

type ActionResponse = { Ok: bool; Error: string option }

type DigestRequest = { Email: string; Language: string }

/// Shared HTTP route convention for the remoting API. Both the server and the
/// client build routes through this function so they can never drift apart.
module ApiRoutes =
    let prefix = "/api"
    let build typeName methodName = sprintf "%s/%s/%s" prefix typeName methodName

/// The typed API shared by the server and the Fable client (Fable.Remoting).
type IW3ChessApi =
    { CreateGame: CreateGameRequest -> Async<Result<PlayerCredentials, string>>
      JoinGame: JoinGameRequest -> Async<Result<PlayerCredentials, string>>
      GetGame: string -> Async<GameView option>
      ValidateToken: string -> string -> Async<PlayerCredentials option>
      SubmitMove: SubmitMoveRequest -> Async<ActionResponse>
      Resign: ActionRequest -> Async<ActionResponse>
      OfferDraw: ActionRequest -> Async<ActionResponse>
      AcceptDraw: ActionRequest -> Async<ActionResponse>
      ListOpenGames: unit -> Async<OpenGame[]>
      RequestDigest: DigestRequest -> Async<ActionResponse> }
