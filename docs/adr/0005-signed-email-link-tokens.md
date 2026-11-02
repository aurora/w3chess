# ADR 0005: Authorise email links with signed tokens

- **Status:** Accepted
- **Date:** 2026-11-02

## Context

The legacy application emails a plaintext per-move password that is appended to a
resume URL; anyone holding the link (or the mail) can act on the game. The reboot
still wants frictionless links from email, but must not store or transmit
guessable secrets, and must keep the server as the authority on every move.

## Decision

Generate a per-game, per-player random secret at game creation. Email links carry a
**signed token** (HMAC-SHA256 over game id, player id and purpose) derived from
that secret. The link endpoint validates the signature and establishes the player's
access to the game; every move is still re-validated server-side. Tokens are
long-lived by default (so forwarded mail keeps working) and can optionally rotate
per move via configuration.

## Consequences

- Positive: no plaintext password in URLs or stored mail; no per-request DB lookup
  for an HMAC token.
- Positive: the same mechanism can later gate the online (SignalR) game view.
- Negative: a forwarded email grants access until the secret is rotated; mitigated
  by optional rotation and by server-side move validation.

## Alternatives considered

- **Keep the legacy plaintext password:** simple but leaks a reusable secret.
  Rejected.
- **Single-use, DB-stored tokens:** stronger replay protection, but links in old
  mail expire and every click needs a DB write. Considered as an option, not the
  default.
