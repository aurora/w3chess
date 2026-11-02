# AGENTS.md

Guidance for AI agents and humans working in this repository.

## Project

W3Chess is a correspondence-chess web application: F# on .NET 10 with Kestrel.
UI is a Fable + Feliz (React) SPA. Persistence is Dapper + SQLite via SQL kept
close to ANSI. Email is sent with MailKit. The app is a full reboot of a legacy
C/CGI chess server; only the **application logic and chess rules** are
reimplemented — no C source code is copied.

## Environment & tooling

- **.NET 10 SDK** is installed at `~/.dotnet/dotnet` and is **not on `PATH`**.
  Use `~/.dotnet/dotnet ...` or add `~/.dotnet` to `PATH` for the session.
- **Node / npm / npx are not installed on the host.** Use the distrobox
  container `ubuntu` (Node v25.9.0). The container shares the home directory and
  runs as the same uid, so `node_modules` are owned correctly:
  ```sh
  distrobox enter ubuntu -- bash -lc 'cd /var/home/harald/source/projects/w3chess/src/W3Chess.Client && npm install'
  ```
  Use this for Vite, Tailwind, `npm install/test/build`, and MailDev.
- **Fable** is a local dotnet tool (`.config/dotnet-tools.json`):
  `~/.dotnet/dotnet tool restore` then `~/.dotnet/dotnet fable`.
- **MailDev** for local email testing: SMTP on `localhost:1025`, web UI on
  `http://localhost:1080`. Start with `npx maildev` (inside the `ubuntu`
  container). No real SMTP account is needed for development.
- **Tests:** `~/.dotnet/dotnet test`.

## Required practices (non-negotiable)

### 1. Documentation language
All documentation, code comments, commit messages, ADRs and plans are written in
**English**. User-facing UI strings exist in **English and German**.

### 2. Architecture Decision Records (ADRs)
Every significant or hard-to-reverse decision gets an ADR.

- Location: `docs/adr/`; filename `NNNN-short-title.md`, numbered sequentially
  (e.g. `0001-use-fable-feliz-for-the-ui.md`).
- Format (MADR-lite), in English:
  1. **Title**
  2. **Status** — Proposed / Accepted / Superseded / Deprecated
  3. **Date**
  4. **Context** — the problem and forces
  5. **Decision** — what we will do, in active voice
  6. **Consequences** — positive and negative
  7. **Alternatives considered** — and why rejected
- Never delete an ADR. Supersede it with a new one and set the old one's status.
- Seed ADRs exist for decisions already made: Fable + Feliz UI, own F# chess
  engine, Dapper + SQLite, MailKit + MailDev, signed email-link tokens, i18n.

### 3. Living implementation plan
- Maintained in the repository at `docs/implementation-plan.md`.
- Keep it **current**: tick items as they are completed, record deviations and
  newly discovered work, and add dated notes for scope changes.
- It is the source of truth for milestone progress; do not let it drift from the
  code.

### 4. Small commits
- Commit in small, coherent, reviewable increments — one logical change per
  commit.
- Use Conventional Commits with an optional scope:
  `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`, `build:`
  (e.g. `feat(engine): generate legal moves for castling`).
- Subject in imperative mood, <= 72 characters; body in English.
- Every commit message ends with the required trailer:
  ```
  Co-authored-by: CommandCodeBot <noreply@commandcode.ai>
  ```
- Do not commit build artifacts (`bin/`, `obj/`, `node_modules/`, `dist/`).
- Run the relevant checks (`dotnet test`, client build) before committing.

### 5. Internationalization (i18n) — required from the start
The UI must support **English and German in the first milestone**. Never hardcode
user-facing strings in components.

- **Client:** central translation module `src/W3Chess.Client/src/I18n.fs` with
  `En` and `De` dictionaries and a `Translations` record type. The UI exposes a
  language switch; the selected language is persisted in `localStorage`, defaults
  to the browser language, and can be overridden with `?lang=en|de`.
- **Server:** email templates and API-visible messages are localized. Each player
  has a preferred language; fall back to the request/browser language, then
  English.
- Add every new string to both languages in the same commit. Missing keys fall
  back to English.
- Chess notation (SAN/PGN) stays language-neutral; localized piece names and
  descriptions are a separate mapping.

### 6. Code style
- `W3Chess.Domain` is pure and Fable-compatible: no IO, no framework types,
  immutable data.
- The server re-validates every move; client-side validation is UX only.
- Comments only where the logic is not self-evident; no redundant narration.
- Follow existing patterns and keep the domain engine independent of ASP.NET,
  Dapper, MailKit, etc.

## Target repository layout

```
src/
  W3Chess.Domain/        # pure, Fable-compatible rules + game model + legacy import
  W3Chess.Server/        # ASP.NET Core (Kestrel), storage, services, API
  W3Chess.Client/        # Fable + Feliz + Vite + Tailwind
  W3Chess.Domain.Tests/  # xUnit
docs/adr/                # Architecture Decision Records
docs/implementation-plan.md
```

## Common commands

```sh
# .NET
~/.dotnet/dotnet build
~/.dotnet/dotnet test

# Fable client (naming may evolve with the project files)
~/.dotnet/dotnet tool restore
~/.dotnet/dotnet fable src/W3Chess.Client --run vite

# Node tooling (host has no Node)
distrobox enter ubuntu -- bash -lc 'cd <dir> && npm install'
distrobox enter ubuntu -- bash -lc 'cd <dir> && npm run dev'

# MailDev
distrobox enter ubuntu -- bash -lc 'npx maildev'
```
