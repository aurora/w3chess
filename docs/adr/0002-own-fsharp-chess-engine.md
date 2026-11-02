# ADR 0002: Implement an own chess engine in F#

- **Status:** Accepted
- **Date:** 2026-11-02

## Context

The legacy application ships its own rules code in C (`canmove`, `kingIsAttacked`,
`checkMove`, `wayFree`, `isMate`). The reboot must reimplement chess rules and the
application logic; no C source code is copied. A mature third-party .NET chess
library exists but would hide the rules and pull in an external dependency.

## Decision

Implement a **pure, immutable F# chess engine** in `W3Chess.Domain`, free of IO
and framework types so it is Fable-compatible and shared by client and server. It
covers move generation, castling, en passant, promotion, check, checkmate,
stalemate, the draw conditions derivable from a position, and SAN/UCI/PGN notation.
The legacy C rules are used only as a behavioural reference and are covered by
`perft` tests against known node counts.

## Consequences

- Positive: full control and transparency over the rules; the same code validates
  moves on the client (UX) and on the server (authority).
- Positive: no external chess dependency; the engine is easy to test with `perft`.
- Negative: correctness and performance are our responsibility. Mitigated with
  reference `perft` positions and targeted rule tests.

## Alternatives considered

- **Third-party .NET chess library:** less code to write, but less control, an
  external dependency and a mismatch with the "reimplement the rules" goal.
  Rejected.
