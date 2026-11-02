# ADR 0008: Real-time updates via token-authenticated SSE

- **Status:** Accepted
- **Date:** 2026-11-02

## Context

Correspondence games are usually slow, but when both players happen to be
online a move should appear on the opponent's board immediately instead of
waiting for the email. The application already sends email for every move; the
live channel is an addition, not a replacement.

The client currently talks to the server over plain JSON over `fetch` (ADR
0007), not Fable.Remoting. A full SignalR setup would add a large JS client and
a second protocol, and the Fable 5 ecosystem is fragile (ADR 0007). Moves are
already sent over the REST API, so the live channel only needs server-to-client
push.

## Decision

Expose a **Server-Sent Events** endpoint `GET /api/game/{gameId}/events?token=…`.
The token is the same signed token from the email link (or the stored
credentials created/joined in the browser); the server validates it before
streaming and rejects invalid tokens with 401. It keeps an in-memory
publish/subscribe registry (`GameEvents`) that maps a game to its subscribers.
After every mutating action (join, move, resign, draw offer/accept) the
`GameService` publishes an `updated` event. The client opens an `EventSource`
with its token and reloads the game view when an event arrives. Emails are still
sent on every move.

## Consequences

- Positive: no extra client library and no second protocol; `EventSource` is
  built into the browser and works through the Vite proxy.
- Positive: the live stream is authenticated with the same token model as the
  email link; only game participants can subscribe.
- Negative: the registry is in-memory, so real-time works for a single server
  instance. Horizontal scaling would need a shared backplane; acceptable for
  correspondence-scale traffic.
- Negative: one-way only and no presence/typing information; not needed here.
- The `updated` poke keeps the wire format unchanged: the client simply reuses
  `GetGame`.

## Alternatives considered

- **SignalR:** groups, presence and reconnection are convenient, but it brings a
  large JS client and a second protocol; overkill while moves go over REST.
  Rejected for now.
- **Client polling:** trivial, but wastes requests and adds latency. Rejected.
- **WebSockets:** two-way, but the client never needs to push over the socket.
  Rejected.
