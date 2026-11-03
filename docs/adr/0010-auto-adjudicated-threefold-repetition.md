# 0010: Auto-adjudicate threefold repetition as a draw

## Status

Accepted

## Date

2026-11-03

## Context

FIDE 9.2 lets a player claim a draw when the same position occurs three times;
the game is even automatically drawn at five occurrences (FIDE 9.6b). The
legacy system had no repetition rule at all — draws happened only by explicit
agreement (`[REMIS]` marker), besides mate, resignation and the
agreement-request flow. The F# engine already adjudicates stalemate, the
fifty-move rule and insufficient material automatically, and the client has no
claim UI: a claim-based flow would need new API surface and interaction for a
rare event, and correspondence players are often offline for days, so a claim
the opponent cannot answer quickly is unattractive.

## Decision

When the position after a submitted move occurs for the third time in the game,
the server finishes the game as `1/2-1/2` automatically. Position identity
follows FIDE 9.2: piece placement, side to move, castling rights, and the en
passant square only when an en passant capture is actually legal (a pinned
pawn cannot capture). Detection is a pure domain function
(`Rules.repetitionKey`, `Rules.isThreefoldRepetition`); `SubmitMove` replays
the game from its initial FEN through every stored move plus the new one and
checks the result.

## Consequences

Positive: the last common automatic draw rule is covered; no claim UI is
needed; the detection is pure and unit-testable; imported legacy games are
covered because they replay from the start position.

Negative: players cannot play on into a fivefold repetition against a third
occurrence (a claim under FIDE 9.2 would allow that choice); the replay is
O(game length) per submitted move, which is negligible at correspondence pace.
If a claim flow is introduced later, this ADR should be superseded.

## Alternatives considered

- Claim-based draw per FIDE 9.2: rejected for now — no claim UI, extra API and
  asynchronous answering awkward for correspondence play.
- Fivefold auto-draw only (FIDE 9.6b): too lenient; three occurrences already
  deadlock casual correspondence games.
