# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Honeybee is a PHP terminal UI (TUI) that runs full screen, 24/7, on an office monitor. Over SSH it shows a remote application's storage usage, refreshing every `HONEYBEE_REFRESH_INTERVAL` seconds (default 30). Each Laravel filesystem disk is shown with its partition's capacity. Under each disk, files are grouped into colored categories by file extension. A status bar is always visible and shows the app name, its health (up or down) and its uptime.

Only Laravel is supported for now. The design must still let other frameworks, such as Symfony, be added later without touching the core. Everything Laravel-specific lives behind the framework adapter.

The project is being built feature by feature from `docs/plans/roadmap.md`; F1 (scaffolding and tooling) is done. The design is in `docs/internal/`, and code must follow it unless the user decides otherwise.

## Hard rules

- **Never commit, stage or push** (`git add`, `git commit`, `git push`, etc.). The user does this themselves.
- **Never use sub agents** (the Agent tool, Workflow tool or any delegation) for anything.
- Do not run `composer require` or add dependencies without saying so. Every runtime dependency must be justified in `docs/internal/architecture.md`.
- Target the latest stable PHP (currently 8.5; set `"php": "^8.5"` in `composer.json`). Use modern language features such as enums, readonly classes and properties, first-class callables, `match` and typed constants wherever they are the plain, obvious tool.

## Code style

- Follow PSR-1, PSR-4 (namespace `Basileaelterman\Honeybee\` → `src/`) and PER Coding Style 2.0 (the successor to PSR-12). Add `declare(strict_types=1);` to every file.
- **Every function and method gets a full docblock**, even when native types already say the same thing. It needs a one-line description of what the function does or returns, plus every `@param`, `@return` and `@throws` that applies. Constructors are the exception: a docblock is optional there. When a promoted constructor parameter needs a more precise type than PHP can express (e.g. what an `array` holds), give it an inline `/** @var list<string> */` or a constructor docblock. Because of this, linters must *not* strip "superfluous" phpdoc. Keep `no_superfluous_phpdoc_tags` (and similar rules) disabled in the fixer config.
- Write simple, clear, straightforward code with no clever tricks. If PHP's standard library or language already has it, use it instead of writing a helper or pulling in a package.

## Documentation rules

- `docs/internal/`: developer documentation. When writing or refactoring code, update the matching file here, or add one in the right topic file or subfolder. Keep `docs/internal/README.md` as the index.
- `docs/setup/`: installation and configuration for users. Every setup doc must be linked from `README.md`.
- `docs/plans/`: future ideas and features that are not built yet. Index them in `docs/plans/README.md`.

## Commands

```bash
composer install
bin/honeybee                                   # run the TUI (reads .env in the project root; not implemented yet)
bin/honeybee --version                         # print "Honeybee <version>" (version: Honeybee::VERSION in src/Honeybee.php)
composer test                                  # all tests (vendor/bin/phpunit)
vendor/bin/phpunit --filter CliTest            # single test class or method
vendor/bin/phpunit --testsuite Unit            # one suite: Unit or Integration
composer analyse                               # PHPStan, level max (phpstan.neon.dist)
composer lint                                  # PER-CS 2.0 check, no changes (.php-cs-fixer.dist.php)
composer format                                # apply PER-CS 2.0 fixes (phpdoc stripping disabled)
```

## Architecture (big picture)

Full design: `docs/internal/architecture.md`, `docs/internal/data-collection.md` and `docs/internal/rendering.md`. The key points, which you only get by reading several of those together:

- **Honeybee runs locally, the app runs remotely.** All app data comes over SSH via the system `ssh` binary (`proc_open` with an argument array, so no local shell is involved). It uses `BatchMode=yes`, so a password prompt can never hijack the TUI, and an OpenSSH ControlMaster socket so each refresh reuses one connection. Nothing is installed on the app server.
- **Framework adapters.** A `FrameworkAdapter` interface supplies the framework-specific parts: the remote command that discovers the app name and its storage locations, and the default health URL. `Laravel\LaravelAdapter` is the only implementation. Adapters return plain value objects, so the core, the collectors and the TUI never know which framework is in use.
- **Discovery vs scan.** *Discovery* runs once at startup and after every reconnect. It runs `php -r` in the remote app directory, boots Laravel and prints JSON with `app.name`, `app.url` and each disk's `driver` and `root`. Only those fields may be selected on the remote side, because credentials (S3 keys, etc.) must never leave the server. A *scan* runs every refresh interval, per local-driver disk: `df -Pk` gives partition capacity and `find … -printf | awk` returns totals per extension. Aggregation happens on the server so output stays small. Disks with other drivers (s3, ftp, …) are listed as "not measured".
- **Nested disk roots.** In older Laravel apps the `public` disk root (`storage/app/public`) sits inside the `local` root (`storage/app`). A scan must prune any other disk's root from its `find` so no file is counted twice.
- **Categories come from extensions only** (`FileCategory` enum: extension map, label, color). Detecting MIME types remotely would be too expensive. Category colors are fixed in the enum, not in config.
- **The main loop never blocks.** One loop ticks about once a second. It reads non-blocking pipes from running SSH processes, starts a new scan when one is due (never while one is still running), runs the HTTP health check (short timeout) and re-renders. The clock and uptime keep moving while a slow `find` runs.
- **Built to run 24/7.** SSH failures, timeouts and bad output must never crash the app. Show the last known data with a "stale since …" marker and keep retrying. Restore the terminal (leave the alternate screen, show the cursor, reset `stty`) on every exit path: normal quit, SIGINT/SIGTERM via `pcntl`, and shutdown functions.
- **Rendering.** Frames go into a `soloterm/screen` virtual buffer, and only the lines that changed are written out, which avoids flicker on a screen that is always on. Everything must fit on one screen and nothing ever scrolls. On SIGWINCH the layout is recomputed, and when space runs short, small categories are collapsed into "Other" instead of overflowing.
- **Health and uptime.** An HTTP GET from the local machine to `HONEYBEE_HEALTH_URL` (Laravel's `/up` by default) checks health; a 2xx response means up. Uptime is the time since Honeybee last saw the app go from down to up. It is saved to a small local state file so restarting Honeybee does not reset it, and shown as days, hours and minutes.
