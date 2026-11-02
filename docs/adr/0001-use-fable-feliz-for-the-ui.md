# ADR 0001: Use Fable + Feliz (React) for the client

- **Status:** Accepted
- **Date:** 2026-11-02

## Context

The reboot needs a browser UI for a correspondence-chess application that is
comfortable on desktop and tablet. The visual designs come from Tailwind-based
HTML mockups. The team wants to stay in F# across the stack and avoid a separate
JavaScript/TypeScript codebase.

## Decision

Build the UI as a single-page application with **Fable 4 + Feliz (React)**,
bundled by **Vite** and styled with **Tailwind CSS**. The frontend talks to the
Kestrel backend over a typed F# API (Fable.Remoting) and shares the pure
`W3Chess.Domain` assembly for board rendering and client-side move hints.

## Consequences

- Positive: one language for the whole stack; the chess engine and DTOs are
  shared with the server; the interactive board is straightforward to build.
- Positive: the mockups' Tailwind design tokens map directly onto Feliz classes.
- Negative: a Node toolchain (Vite, Tailwind) is required. The host has no Node,
  so all Node commands run inside the distrobox container `ubuntu`.
- Negative: the SPA requires a JSON/remoting API surface instead of server-rendered
  HTML.

## Alternatives considered

- **Server-rendered + htmx:** simplest deployment, but less interactive board and
  a split between F# and HTML templates. Rejected in favour of an all-F# client.
- **Blazor Server:** all .NET, but a persistent SignalR circuit per user and a
  heavier model for a page that is read slowly and intermittently. Rejected.
