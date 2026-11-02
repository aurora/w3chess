namespace W3Chess.Server.Api

open W3Chess.Contracts
open W3Chess.Server.Services

/// Implements the shared API contract on top of `GameService`.
module ServerApi =
    let create (gameService: GameService) : IW3ChessApi =
        { CreateGame = fun request -> gameService.CreateGame request
          JoinGame = fun request -> gameService.JoinGame request
          GetGame = fun gameId -> gameService.GetGame gameId
          ValidateToken = fun gameId token -> gameService.ValidateToken(gameId, token)
          SubmitMove = fun request -> gameService.SubmitMove request
          Resign = fun request -> gameService.Resign request
          OfferDraw = fun request -> gameService.OfferDraw request
          AcceptDraw = fun request -> gameService.AcceptDraw request
          ListOpenGames = fun () -> gameService.ListOpenGames()
          RequestDigest = fun request -> gameService.RequestDigest request }
