# Implementation Plan

Living plan for the W3Chess reboot. Keep this file current: tick items as they
are completed, record deviations and newly discovered work, and add dated notes
for scope changes. See `AGENTS.md` for the working rules and `docs/adr/` for the
decisions behind this plan.

## Goal

A correspondence-chess web application that is played by email, rebuilt in F# on
.NET 10 with Kestrel. Only the application logic and the chess rules are
reimplemented — no C source code is copied. Design basis:
`studio_dark_spielansicht_grandmaster` (game view) and
`zen_schach_lobby_neue_partie` (lobby), with an additional light theme
(tokens from `zen_chessboard/DESIGN.md`).

Decided and recorded in ADRs:

- UI: Fable 4 + Feliz (React) SPA, Vite + Tailwind (ADR 0001)
- Rules: own pure F# engine (ADR 0002)
- Persistence: Dapper + SQLite, ANSI-leaning SQL (ADR 0003)
- Email: MailKit, MailDev for local testing (ADR 0004)
- Authorisation: signed email-link tokens (ADR 0005)
- i18n: English and German from the first milestone (ADR 0006)

## Target layout

```
src/
  W3Chess.Domain/        # pure, Fable-compatible rules + game model + legacy import
  W3Chess.Server/        # ASP.NET Core (Kestrel), storage, services, API
  W3Chess.Client/        # Fable + Feliz + Vite + Tailwind
  W3Chess.Domain.Tests/  # xUnit
docs/adr/                # Architecture Decision Records
```

## Milestone 1 — playable by email

### Foundation
- [x] Solution scaffold (`.slnx`, `Directory.Build.props`, `.gitignore`)
- [x] Agent guidelines (`AGENTS.md`) and seed ADRs (`docs/adr/0001..0006`)

### Domain engine (`W3Chess.Domain`)
- [x] Types: colour, piece, square, position, move
- [x] FEN parse/format, start position, legacy board conversion
- [x] Move generation: pawn (incl. double push, en passant, promotion), knight,
      bishop, rook, queen, king, castling
- [x] Attack detection, legal-move filtering, check / checkmate / stalemate,
      fifty-move and insufficient-material draws
- [x] Notation: UCI, SAN (disambiguation, check/mate markers), PGN
- [x] Tests: `perft` (start, Kiwipete, an en-passant/promotion position) and
      targeted rule tests — green
- [x] Legacy text-file importer (`LegacyImport.fs`) with replay validation

### Server (`W3Chess.Server`)
- [x] Kestrel host, configuration, DI
- [x] Schema + idempotent migration runner; Dapper repositories
- [x] Game service: new, join, resume, move, resign, draw, message
- [x] Token service (signed email links)
- [x] Email service (MailKit) + templates (EN/DE); MailDev dev default
- [x] REST/remoting API for the client
- [x] Legacy import endpoint/CLI
- [x] `Notifier` abstraction (email now, realtime later)
- [x] Integration tests for the full game flow

### Client (`W3Chess.Client`)
- [x] Fable + Feliz + Vite + Tailwind project setup
- [x] Theme layer: dark (studio) and light (zen) tokens, persisted switch
- [x] i18n: `I18n.fs` with `En`/`De`, language switch, `?lang=` override
- [x] Board component (click selection, legal-move hints, last-move and flip)
- [x] Lobby: new game, resume, digest request, open tables
- [x] Game view: match strip, board, move console, PGN history, actions
- [x] Archive: list the games for an email in the browser (no email wait)

### Cross-cutting
- [x] English and German for all UI strings and email templates in the same
      commit that introduces them

## Later (not in M1)

- Real-time updates are implemented via token-authenticated SSE (ADR 0008): a
  move appears live for an online opponent, while emails are still sent.
  SignalR remains an option if presence or groups are needed later.
- Optional AI opponent (neither planned nor excluded).
- Additional draw rules (threefold repetition), clock/ICCF time controls, ELO,
  accounts, admin mode.

## Notes

- 2026-11-02: Plan approved; engine implemented and green. Next: legacy importer,
  then the server storage/service/email layer, then the client.
- 2026-11-02: Legacy importer done and tested. Server storage, game service,
  signed-link tokens, MailKit email (EN/DE), import CLI and the Fable.Remoting
  API implemented; integration tests green; `/health` and the remoting endpoint
  verified against a running Kestrel host. Next: the Fable client (project setup,
  theme layer, i18n, board, lobby, game view).
- 2026-11-02: Fable + Feliz client implemented with Vite and Tailwind: typed
  remoting client, credentials in `localStorage`, dark/light theme, EN/DE i18n
  with browser detection and `?lang=` override, board with legal-move hints,
  lobby and game view. `dotnet fable` and `vite build` succeed. Still open: the
  archive page, and an end-to-end run against MailDev (server + client together).
- 2026-11-02: Found that `Fable.Remoting.Client`'s reflection decoder breaks under
  Fable 5 (options/lists misread as unions). Replaced the client API with explicit
  `fetch` + `JSON.parse` decoding (ADR 0007); collections cross the wire as
  arrays. Verified end-to-end against the running server (list, create, get,
  validate, submit move). Still open: archive page; manual browser/MailDev pass.
- 2026-11-02: Added drag and drop (click-to-move kept) and the archive: a server
  endpoint lists a player's games by email and the lobby shows them in the
  browser. Email flow verified end to end against MailDev (invitation and turn
  mails in German, link token validates, move notifies both players).
- 2026-11-02: Board polish: rank/file coordinates, captured pieces with material
  advantage, a PGN move table, and a promotion overlay on the board.
- 2026-11-02: Real-time updates: token-authenticated SSE per game (ADR 0008)
  with an in-memory pub/sub; the game view reloads on `updated`. Verified the
  endpoint with curl (401 for a bad token, `updated` after a move).
