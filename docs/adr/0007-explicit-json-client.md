# ADR 0007: Decode API responses explicitly on the client

- **Status:** Accepted
- **Date:** 2026-11-02

## Context

The client originally used `Fable.Remoting.Client` for typed calls. Under the
Fable 5 compiler, `Fable.SimpleJson` (the reflection-based decoder that
`Fable.Remoting.Client` uses) misclassifies F# `option` and `list` values: it
reports them as unions (`None`/`Some`, `Empty`/`Cons`) because
`FSharpType.IsUnion` returns true for them. The server serialises those types as
`null`/value and JSON arrays, so decoding fails with errors such as
`Cannot convert null to Union` and `Expected JSON … to match the type FSharpList`.
There is no newer `Fable.SimpleJson` release, and downgrading to Fable 4 breaks
the Feliz compiler plugin.

## Decision

Keep `Fable.Remoting.Giraffe` on the server (the wire format is plain JSON:
records as objects, nullary unions as strings, options as `null`/value, lists as
arrays, `Result` as `{"Ok": …}` / `{"Error": …}`), and implement the **client**
against that wire format with `fetch` and explicit `JSON.stringify` / `JSON.parse`
decoding in `Api.fs`. Collections cross the boundary as arrays.

## Consequences

- Positive: decoding no longer depends on Fable reflection, so it works under
  Fable 5 and cannot regress with a compiler change.
- Positive: the client bundle is smaller (no `Fable.Remoting.Client`,
  `Fable.SimpleJson`, `Fable.Remoting.MsgPack`).
- Negative: request encoders and response decoders are written and maintained by
  hand; they must be kept in sync with the DTOs.
- Positive: the server and its integration tests are unchanged.

## Alternatives considered

- **Use Fable 4:** the compiler runs on .NET 10 via roll-forward, but Feliz'
  compiler plugin cannot be loaded, so the client does not build. Rejected.
- **Remove options/lists from the contract only:** lists could become arrays, but
  options are pervasive (`string option`, `PlayerView option`) and remain broken;
  not sufficient.
- **Replace Fable.Remoting on both sides with a hand-written REST/JSON API:** more
  work for no additional benefit while the server side already produces clean
  JSON. Deferred.
