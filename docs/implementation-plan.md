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
- [ ] Kestrel host, configuration, DI
- [ ] Schema + idempotent migration runner; Dapper repositories
- [ ] Game service: new, join, resume, move, resign, draw, message
- [ ] Token service (signed email links)
- [ ] Email service (MailKit) + templates (EN/DE); MailDev dev default
- [ ] REST/remoting API for the client
- [ ] Legacy import endpoint/CLI
- [ ] `Notifier` abstraction (email now, realtime later)

### Client (`W3Chess.Client`)
- [ ] Fable + Feliz + Vite + Tailwind project setup
- [ ] Theme layer: dark (studio) and light (zen) tokens, persisted switch
- [ ] i18n: `I18n.fs` with `En`/`De`, language switch, `?lang=` override
- [ ] Board component (highlighting, drag/click, flip), legal-move hints
- [ ] Lobby: new game, resume, digest request, open tables
- [ ] Game view: match strip, board, move console, PGN history, actions
- [ ] Archive

### Cross-cutting
- [ ] English and German for all UI strings and email templates in the same
      commit that introduces them

## Later (not in M1)

- Real-time online play via SignalR when both players are online (emails still
  sent); the `Notifier` abstraction is the seam.
- Optional AI opponent (neither planned nor excluded).
- Additional draw rules (threefold repetition), clock/ICCF time controls, ELO,
  accounts, admin mode.

## Notes

- 2026-11-02: Plan approved; engine implemented and green. Next: legacy importer,
  then the server storage/service/email layer, then the client.
