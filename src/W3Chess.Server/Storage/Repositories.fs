namespace W3Chess.Server.Storage

open System
open Dapper
open Microsoft.Data.Sqlite

module Db =
    let connect (databasePath: string) =
        let connection = new SqliteConnection(sprintf "Data Source=%s" databasePath)
        connection.Open()
        connection

    let newId () = Guid.NewGuid().ToString("n")
    let nowIso () = DateTime.UtcNow.ToString("o")

module PlayerRepository =
    let insert (connection: SqliteConnection) (player: PlayerRow) =
        connection.Execute(
            """INSERT INTO players (id, nickname, email, language, created_at)
               VALUES (@Id, @Nickname, @Email, @Language, @CreatedAt)""",
            player
        )
        |> ignore

    let findById (connection: SqliteConnection) (id: string) : PlayerRow option =
        connection.Query<PlayerRow>(
            """SELECT id AS Id, nickname AS Nickname, email AS Email, language AS Language, created_at AS CreatedAt
               FROM players WHERE id = @id""",
            {| id = id |}
        )
        |> Seq.tryHead

    let findByEmail (connection: SqliteConnection) (email: string) =
        connection.Query<PlayerRow>(
            """SELECT id AS Id, nickname AS Nickname, email AS Email, language AS Language, created_at AS CreatedAt
               FROM players WHERE lower(email) = lower(@email)""",
            {| email = email |}
        )
        |> Seq.toList

module GameRepository =
    let insert (connection: SqliteConnection) (game: GameRow) =
        connection.Execute(
            """INSERT INTO games
                 (id, status, result, initial_fen, current_fen, side_to_move, move_count,
                  created_at, updated_at, last_message, draw_offer_by)
               VALUES
                 (@Id, @Status, @Result, @InitialFen, @CurrentFen, @SideToMove, @MoveCount,
                  @CreatedAt, @UpdatedAt, @LastMessage, @DrawOfferBy)""",
            game
        )
        |> ignore

    let update (connection: SqliteConnection) (game: GameRow) =
        connection.Execute(
            """UPDATE games SET
                 status = @Status,
                 result = @Result,
                 current_fen = @CurrentFen,
                 side_to_move = @SideToMove,
                 move_count = @MoveCount,
                 updated_at = @UpdatedAt,
                 last_message = @LastMessage,
                 draw_offer_by = @DrawOfferBy
               WHERE id = @Id""",
            {| Id = game.Id
               Status = game.Status
               Result = game.Result
               CurrentFen = game.CurrentFen
               SideToMove = game.SideToMove
               MoveCount = game.MoveCount
               UpdatedAt = game.UpdatedAt
               LastMessage = game.LastMessage
               DrawOfferBy = game.DrawOfferBy |}
        )
        |> ignore

    let private columns =
        """id AS Id, status AS Status, result AS Result, initial_fen AS InitialFen,
           current_fen AS CurrentFen, side_to_move AS SideToMove, move_count AS MoveCount,
           created_at AS CreatedAt, updated_at AS UpdatedAt, last_message AS LastMessage,
           draw_offer_by AS DrawOfferBy"""

    let findById (connection: SqliteConnection) (id: string) : GameRow option =
        connection.Query<GameRow>(
            sprintf "SELECT %s FROM games WHERE id = @id" columns,
            {| id = id |}
        )
        |> Seq.tryHead

    let listOpen (connection: SqliteConnection) =
        connection.Query<GameRow>(
            sprintf "SELECT %s FROM games WHERE status = 'waiting' ORDER BY created_at DESC" columns
        )
        |> Seq.toList

    let listForEmail (connection: SqliteConnection) (email: string) =
        connection.Query<GameRow>(
            sprintf
                """SELECT %s FROM games g
                   WHERE EXISTS (
                       SELECT 1 FROM game_players gp
                       JOIN players p ON p.id = gp.player_id
                       WHERE gp.game_id = g.id AND lower(p.email) = lower(@email))
                   ORDER BY g.updated_at DESC"""
                columns,
            {| email = email |}
        )
        |> Seq.toList

    let listOlderThan (connection: SqliteConnection) (cutoffIso: string) =
        connection.Query<GameRow>(
            sprintf "SELECT %s FROM games WHERE updated_at < @cutoff" columns,
            {| cutoff = cutoffIso |}
        )
        |> Seq.toList

    let delete (connection: SqliteConnection) (id: string) =
        connection.Execute("DELETE FROM games WHERE id = @id", {| id = id |}) |> ignore
        connection.Execute("DELETE FROM game_players WHERE game_id = @id", {| id = id |}) |> ignore
        connection.Execute("DELETE FROM moves WHERE game_id = @id", {| id = id |}) |> ignore

module GamePlayerRepository =
    let insert (connection: SqliteConnection) (gamePlayer: GamePlayerRow) =
        connection.Execute(
            """INSERT INTO game_players (game_id, player_id, color, token_secret, notify_by_email)
               VALUES (@GameId, @PlayerId, @Color, @TokenSecret, @NotifyByEmail)""",
            gamePlayer
        )
        |> ignore

    let private columns =
        "game_id AS GameId, player_id AS PlayerId, color AS Color, token_secret AS TokenSecret, notify_by_email AS NotifyByEmail"

    let listByGame (connection: SqliteConnection) (gameId: string) =
        connection.Query<GamePlayerRow>(
            sprintf "SELECT %s FROM game_players WHERE game_id = @gameId" columns,
            {| gameId = gameId |}
        )
        |> Seq.toList

    let findByGameAndPlayer (connection: SqliteConnection) (gameId: string) (playerId: string) : GamePlayerRow option =
        connection.Query<GamePlayerRow>(
            sprintf "SELECT %s FROM game_players WHERE game_id = @gameId AND player_id = @playerId" columns,
            {| gameId = gameId; playerId = playerId |}
        )
        |> Seq.tryHead

    let findByGameAndEmail (connection: SqliteConnection) (gameId: string) (email: string) : GamePlayerRow option =
        connection.Query<GamePlayerRow>(
            sprintf
                "SELECT %s FROM game_players gp JOIN players p ON p.id = gp.player_id WHERE gp.game_id = @gameId AND lower(p.email) = lower(@email)"
                columns,
            {| gameId = gameId; email = email |}
        )
        |> Seq.tryHead

module MoveRepository =
    let insert (connection: SqliteConnection) (move: MoveRow) =
        connection.Execute(
            """INSERT INTO moves (game_id, ply, uci, san, fen_after, message, created_at)
               VALUES (@GameId, @Ply, @Uci, @San, @FenAfter, @Message, @CreatedAt)""",
            move
        )
        |> ignore

    let listByGame (connection: SqliteConnection) (gameId: string) =
        connection.Query<MoveRow>(
            """SELECT game_id AS GameId, ply AS Ply, uci AS Uci, san AS San,
                      fen_after AS FenAfter, message AS Message, created_at AS CreatedAt
               FROM moves WHERE game_id = @gameId ORDER BY ply ASC""",
            {| gameId = gameId |}
        )
        |> Seq.toList

module AccountRepository =
    let insert (connection: SqliteConnection) (account: AccountRow) =
        connection.Execute(
            """INSERT INTO accounts (id, email, nickname, language, created_at)
               VALUES (@Id, @Email, @Nickname, @Language, @CreatedAt)""",
            account
        )
        |> ignore

    let private columns = "id AS Id, email AS Email, nickname AS Nickname, language AS Language, created_at AS CreatedAt"

    let findById (connection: SqliteConnection) (id: string) : AccountRow option =
        connection.Query<AccountRow>(sprintf "SELECT %s FROM accounts WHERE id = @id" columns, {| id = id |})
        |> Seq.tryHead

    let findByEmail (connection: SqliteConnection) (email: string) : AccountRow option =
        connection.Query<AccountRow>(
            sprintf "SELECT %s FROM accounts WHERE email = @email COLLATE NOCASE" columns,
            {| email = email |}
        )
        |> Seq.tryHead

module LoginTokenRepository =
    let insert (connection: SqliteConnection) (row: LoginTokenRow) =
        connection.Execute(
            """INSERT INTO login_tokens (token_hash, account_id, created_at, expires_at, used_at)
               VALUES (@TokenHash, @AccountId, @CreatedAt, @ExpiresAt, @UsedAt)""",
            row
        )
        |> ignore

    let findUnused (connection: SqliteConnection) (tokenHash: string) : LoginTokenRow option =
        connection.Query<LoginTokenRow>(
            """SELECT token_hash AS TokenHash, account_id AS AccountId, created_at AS CreatedAt,
                      expires_at AS ExpiresAt, used_at AS UsedAt
               FROM login_tokens WHERE token_hash = @tokenHash AND used_at = ''""",
            {| tokenHash = tokenHash |}
        )
        |> Seq.tryHead

    let markUsed (connection: SqliteConnection) (tokenHash: string) (usedAt: string) =
        connection.Execute(
            "UPDATE login_tokens SET used_at = @usedAt WHERE token_hash = @tokenHash",
            {| tokenHash = tokenHash; usedAt = usedAt |}
        )
        |> ignore

    /// Deletes tokens that have expired or already been used; returns the
    /// number of removed rows.
    let deleteExpiredAndUsed (connection: SqliteConnection) (nowIso: string) : int =
        connection.Execute(
            "DELETE FROM login_tokens WHERE expires_at < @now OR used_at <> ''",
            {| now = nowIso |}
        )
