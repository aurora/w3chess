# W3Chess

A small, dependency-free web app for playing correspondence chess by email — no accounts, no database, just a link in your inbox and a board in your browser. This repository carries forward **Sir Toby's original W3Chess** (1998–2004) as an idiomatic, modern PHP 8 application.

## Why this exists

Years ago I had the original C/CGI version of W3Chess installed on a server, and used it to play chess with my father over the internet — a game per email, at whatever pace real life allowed. It was, and still is, about the best tool I've come across for that particular use case: no accounts to create, no app to install, just a board and a "your move" email. It quietly did its job for a long time.

Then my current webspace provider stopped allowing custom-compiled CGI binaries, which is exactly what `w3chess.c` needs to run. Rather than lose the tool, I had it ported to PHP — first a deliberately literal, line-for-line AI port to get it running again, and then a proper idiomatic refactor (this codebase) into modern, typed, class-based PHP 8, done with Claude Code. The two-step process is visible in the git history if you're curious: [`f806804`](../../commit/f806804) is the original C source, [`5e0e190`](../../commit/5e0e190) is the 1:1 PHP port, and everything after is the refactor.

None of this is a judgment on the original — quite the opposite. W3Chess earns this effort *because* it's a well-scoped, well-built piece of software that has kept working, unglamorously, for over two decades. If Sir Toby ever stumbles across this repository: thank you for building something this durable, and I hope this port does it justice. Full credit for the design, the rules engine, and the whole idea stays with the original author; this repository only changes *how* it's built, not *what* it does.

## What it is

- **Correspondence chess over email.** Two players, one board, moves exchanged as plain emails with a resume link and a per-move password — no login required.
- **Classic request/response.** Every page is a regular form GET/POST; there is no client-side JavaScript or AJAX. This is deliberate — the original's flow is preserved exactly.
- **Zero runtime dependencies.** Composer is used only for autoloading (and PHPUnit in development); there is no framework underneath.
- **File-based storage.** Each game is one flat file under `games/`, in the same on-disk format the original C and 1:1-PHP versions used — existing in-flight games keep working.
- **i18n.** English, German, Spanish, Italian and Portuguese (BR), selected automatically from the browser's `Accept-Language` header.

## What changed in the refactor

The 1:1 PHP port (still visible in git history as `w3chess.php`) was intentionally a literal transliteration of the C source: global variables, a deeply nested if/else request router, raw `printf()`-built HTML, hand-rolled URL-decoding, and no output escaping. This refactor turns that into a small, typed, class-based application:

- **Structure**: a `public/index.php` front controller, a `Router` dispatching to one `Action` class per `ACTION=` value, `.phtml` templates instead of inline `printf()`, and the chess rules, game storage, mail composition and i18n each isolated into their own namespace under `src/`.
- **Security hardening** (agreed as in-scope for this refactor): all dynamic output is now HTML-escaped, game IDs are validated before touching the filesystem, mail headers are sanitised against header injection, game-file writes are flock()'d, and the admin password moved from DES-`crypt()` to `password_hash()`/`password_verify()`.
- **Preserved on purpose**: the on-disk game format, the request/response flow, the chess rule engine's exact behaviour (including a couple of original quirks that are load-bearing for existing games — documented inline where they matter), and the overall visual layout.

## Requirements

- PHP 8.2+
- Composer (for autoloading; `composer install` before first run)
- A `sendmail`-compatible binary for outgoing mail (configurable in `config/config.php`)
- Any web server that can run PHP-FPM, with `public/` as the document root

## Getting started

```bash
composer install
php -S localhost:8000 -t public
```

Then open `http://localhost:8000/`. Game data lives in `games/` (outside the web root); configuration is in `config/config.php` and `config/defaults.php`.

## Tests

```bash
vendor/bin/phpunit
```

Covers the chess rules engine (move legality, check/checkmate detection) and the game-file repository's round-trip fidelity against a real sample game.

## License

GPL-2.0-or-later, same as the original — see [`COPYING.TXT`](COPYING.TXT).

## Credits

- **Original design, rules engine and C/CGI implementation**: Tobias "Sir Toby" Mueller, 1998–2004 ([w3chess.sourceforge.net](http://w3chess.sourceforge.net/)).
- **PHP 8 port and refactor**: Harald Lapp, with Claude Code.
