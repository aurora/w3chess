namespace W3Chess.Server.Storage

open Microsoft.Data.Sqlite

/// Storage row types. `CLIMutable` gives Dapper a parameterless constructor and
/// settable properties; lookups return options instead of nulls.
[<CLIMutable>]
type PlayerRow =
    { Id: string
      Nickname: string
      Email: string
      Language: string
      CreatedAt: string }

[<CLIMutable>]
type GameRow =
    { Id: string
      Status: string
      Result: string
      InitialFen: string
      CurrentFen: string
      SideToMove: string
      MoveCount: int
      CreatedAt: string
      UpdatedAt: string
      LastMessage: string
      DrawOfferBy: string }

[<CLIMutable>]
type GamePlayerRow =
    { GameId: string
      PlayerId: string
      Color: string
      TokenSecret: string
      NotifyByEmail: int }

[<CLIMutable>]
type MoveRow =
    { GameId: string
      Ply: int
      Uci: string
      San: string
      FenAfter: string
      Message: string
      CreatedAt: string }

[<CLIMutable>]
type AccountRow =
    { Id: string
      Email: string
      Nickname: string
      Language: string
      CreatedAt: string }

[<CLIMutable>]
type LoginTokenRow =
    { TokenHash: string
      AccountId: string
      CreatedAt: string
      ExpiresAt: string
      UsedAt: string }

module Schema =
    let private statements =
        [ """CREATE TABLE IF NOT EXISTS players (
                id TEXT PRIMARY KEY,
                nickname TEXT NOT NULL,
                email TEXT NOT NULL,
                language TEXT NOT NULL,
                created_at TEXT NOT NULL
             )"""
          """CREATE INDEX IF NOT EXISTS idx_players_email ON players (email)"""
          """CREATE TABLE IF NOT EXISTS games (
                id TEXT PRIMARY KEY,
                status TEXT NOT NULL,
                result TEXT NOT NULL,
                initial_fen TEXT NOT NULL,
                current_fen TEXT NOT NULL,
                side_to_move TEXT NOT NULL,
                move_count INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                last_message TEXT NOT NULL,
                draw_offer_by TEXT NOT NULL
             )"""
          """CREATE INDEX IF NOT EXISTS idx_games_status ON games (status)"""
          """CREATE TABLE IF NOT EXISTS game_players (
                game_id TEXT NOT NULL,
                player_id TEXT NOT NULL,
                color TEXT NOT NULL,
                token_secret TEXT NOT NULL,
                notify_by_email INTEGER NOT NULL,
                PRIMARY KEY (game_id, player_id)
             )"""
          """CREATE INDEX IF NOT EXISTS idx_game_players_player ON game_players (player_id)"""
          """CREATE TABLE IF NOT EXISTS moves (
                game_id TEXT NOT NULL,
                ply INTEGER NOT NULL,
                uci TEXT NOT NULL,
                san TEXT NOT NULL,
                fen_after TEXT NOT NULL,
                message TEXT NOT NULL,
                created_at TEXT NOT NULL,
                PRIMARY KEY (game_id, ply)
             )"""
          """CREATE TABLE IF NOT EXISTS accounts (
                id TEXT PRIMARY KEY,
                email TEXT NOT NULL,
                nickname TEXT NOT NULL,
                language TEXT NOT NULL,
                created_at TEXT NOT NULL
             )"""
          """CREATE UNIQUE INDEX IF NOT EXISTS idx_accounts_email ON accounts (email COLLATE NOCASE)"""
          """CREATE TABLE IF NOT EXISTS login_tokens (
                token_hash TEXT PRIMARY KEY,
                account_id TEXT NOT NULL,
                created_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                used_at TEXT NOT NULL
             )""" ]

    /// Creates all tables and indexes if they do not exist yet.
    let migrate (connection: SqliteConnection) =
        for statement in statements do
            use command = connection.CreateCommand()
            command.CommandText <- statement
            command.ExecuteNonQuery() |> ignore
