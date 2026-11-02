# ADR 0009: Optional passwordless accounts on top of the token model

- **Status:** Accepted
- **Date:** 2026-11-02

## Context

Players can already play a game with the token from their email link (ADR 0005)
and play live over SSE with that token (ADR 0008). This works but is clumsy:
starting many games means juggling per-game credentials, and live play always
needs the token. An identity layer would make "my games", starting games and
live play easier, and prepares for later features (preferences, ELO, presence).

Registration should be **optional by default** and configurable, and the email
token must keep working. The per-game token is long-lived and can be forwarded,
so it must not become a full account credential.

## Decision

Add **passwordless accounts**:

- A login is requested with an email address. If no account exists it is created
  on first successful login (registration = first login).
- The email contains a **single-use, 30-minute login link** (separate from the
  per-game link). Only its SHA-256 hash is stored.
- Completing the link returns a **signed, 30-day session token**
  (`accountId.expiry.signature`), which the client stores locally and sends as a
  bearer credential in request bodies.
- The session is accepted **in addition to** the per-game token for game actions
  and for the SSE subscription: the server resolves the acting player by the
  account's email. The per-game token is unchanged and still authorises exactly
  one game.
- `Auth:RequireAccount` (default `false`) can require an account to create or
  join games. Guest play through a per-game token remains possible.

## Consequences

- Positive: one login for many games; live play needs no token copying; the
  per-game token stays narrow, so forwarded game mails cannot take over an
  account.
- Positive: no passwords to store or leak; unique emails; single-use login
  tokens are hashed and expire.
- Negative: a long-lived session token lives in `localStorage`; acceptable for a
  game but it is bearer-equivalent, so XSS would leak it. No CSRF surface because
  it is not a cookie.
- Negative: game emails do not embed a login link yet; players log in from the
  app. Embedding a separate short-lived account link is a later, small addition.
- Negative: accounts and games are linked by email; duplicate emails are
  prevented by a case-insensitive unique index, but there is no merge flow yet.

## Alternatives considered

- **Cookie-based session:** convenient, but adds CSRF handling and forces CORS
  restrictions. Rejected for a bearer token in request bodies.
- **Mandatory registration:** possible via configuration, but a poor default for
  people who only want to play the game they were invited to. Rejected as
  default.
- **Let the per-game link log into the account:** convenient but lets a
  forwarded, long-lived game link take over the whole account. Rejected in
  favour of a separate login link.
