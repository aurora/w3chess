# ADR 0004: Send email with MailKit and test with MailDev

- **Status:** Accepted
- **Date:** 2026-11-02

## Context

Correspondence chess is driven by email notifications: a player receives a message
with the board and a link back to the game after every move. The legacy app shells
out to `sendmail`; the reboot needs a proper, configurable SMTP client and a way to
test mail flow locally without a real mailbox.

## Decision

Send all mail through **MailKit** over configurable SMTP. In development the SMTP
target is **MailDev** (`localhost:1025`, web UI `http://localhost:1080`), which
captures every message for inspection. Email rendering is isolated behind an
`EmailService` with templates that support the selected language.

## Consequences

- Positive: modern, well-tested SMTP client with STARTTLS and authentication.
- Positive: MailDev gives a fast, offline-friendly feedback loop for the whole
  email-driven game flow.
- Negative: a dev email container/process must be running; documented in
  `AGENTS.md`.

## Alternatives considered

- **Shelling out to `sendmail` (legacy approach):** brittle and platform-specific.
  Rejected.
- **Transactional email API (Resend/SendGrid):** convenient in production but
  requires an account and key, and is heavier for local development. Not needed
  yet; SMTP can be pointed at such a provider later.
