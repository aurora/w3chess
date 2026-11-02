# ADR 0006: Support English and German from the first milestone

- **Status:** Accepted
- **Date:** 2026-11-02

## Context

The application is aimed at German- and English-speaking correspondence players.
The legacy application shipped `lang-en.h` and `lang-de.h`. Retrofitting
translation later would touch every screen, template and API message.

## Decision

Treat internationalization as a **first-milestone requirement**. The client uses a
central translation module with typed `En` and `De` dictionaries, a visible
language switch, `localStorage` persistence, browser-language detection and a
`?lang=en|de` override. Server-side email templates and API-visible messages are
localized per player preference, falling back to the request language and then
English. Chess notation (SAN/PGN) stays language-neutral.

## Consequences

- Positive: no hardcoded user-facing strings; adding a language is additive.
- Positive: emails and UI match the player's language from day one.
- Negative: every new string must be added to both dictionaries in the same
  commit; enforced by review and by a missing-key fallback to English.

## Alternatives considered

- **English only, translate later:** rejected because it would force a costly
  pass over all UI and templates later.
- **External i18n library:** unnecessary for two languages; a small typed module
  keeps the F# record type as the single source of keys.
