namespace W3Chess.Server.Services

open System
open W3Chess.Server
open W3Chess.Server.Storage

/// Deletes games that have not been updated within `GameRetentionDays`.
/// A retention of zero days disables the cleanup entirely.
module Retention =
    let cutoffIso (days: int) : string =
        DateTime.UtcNow.AddDays(float -days).ToString("o")

    /// Removes expired games and returns how many were deleted.
    let cleanupExpired (settings: AppSettings) : Async<int> =
        async {
            if settings.GameRetentionDays <= 0 then
                return 0
            else
                use connection = Db.connect settings.DatabasePath
                let expired = GameRepository.listOlderThan connection (cutoffIso settings.GameRetentionDays)

                for game in expired do
                    GameRepository.delete connection game.Id

                return List.length expired
        }

    /// Removes expired and used login tokens and returns how many were deleted.
    let purgeLoginTokens (settings: AppSettings) : Async<int> =
        async {
            use connection = Db.connect settings.DatabasePath
            return LoginTokenRepository.deleteExpiredAndUsed connection (Db.nowIso ())
        }

    /// Runs the cleanup once immediately and then once a day, for as long as
    /// the process lives.
    let startBackground (settings: AppSettings) (log: string -> unit) : unit =
        let rec loop () =
            async {
                try
                    let! deletedGames = cleanupExpired settings
                    let! deletedTokens = purgeLoginTokens settings

                    if deletedGames > 0 || deletedTokens > 0 then
                        log
                            (sprintf
                                "retention: deleted %d games, %d expired login tokens"
                                deletedGames
                                deletedTokens)
                with ex ->
                    log (sprintf "retention: cleanup failed: %s" ex.Message)

                do! Async.Sleep(TimeSpan.FromHours 24.0)
                return! loop ()
            }

        Async.Start (loop ())
