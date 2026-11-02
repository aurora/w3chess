# ADR 0003: Persist with Dapper + SQLite

- **Status:** Accepted
- **Date:** 2026-11-02

## Context

The legacy application stores each game as a flat text file in a data directory.
The reboot needs reliable, concurrent-safe storage and must be able to import the
existing text files. A future migration to MySQL (or another RDBMS) must stay
possible.

## Decision

Persist games, players, moves and tokens in a relational schema accessed through
**Dapper** over **Microsoft.Data.Sqlite**, with SQL kept close to ANSI to ease a
later move to MySQL or PostgreSQL. The schema is created and migrated by an
idempotent runner. Games are reconstructed from a stored move list plus a
denormalised current FEN for fast reads. A dedicated importer parses the legacy
text files and replays their move records through the engine.

## Consequences

- Positive: transactional, queryable storage with a tiny dependency footprint.
- Positive: the importer makes the legacy data a first-class source.
- Positive: ANSI-leaning SQL and a thin data layer keep an RDBMS swap cheap.
- Negative: hand-written SQL and mapping instead of a full ORM; acceptable for the
  small, well-understood schema.

## Alternatives considered

- **JSON files per game (as in the legacy app):** simple to inspect, but painful
  for concurrency, querying and integrity. Rejected.
- **EF Core + PostgreSQL:** more features and scale, but heavier setup and an
  object-mapping layer we do not need yet. Deferred; may become a future ADR.
