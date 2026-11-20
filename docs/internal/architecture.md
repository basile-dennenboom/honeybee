# Architecture

> Status: mostly design. Only the entry point (roadmap F1) is built; see [Implemented so far](#implemented-so-far). Update this document as the code is written so it describes what exists.

## Overview

```
 monitor machine (Honeybee)                          application server
┌──────────────────────────────────────────┐        ┌──────────────────────────┐
│ bin/honeybee                             │        │                          │
│   └─ Application (main loop, ~1s tick)   │  ssh   │  php -r  (discovery)     │
│        ├─ FrameworkAdapter ──────────────┼───────►│  df -Pk  (capacity)      │
│        ├─ StorageCollector ──────────────┼───────►│  find|awk (per-ext sums) │
│        ├─ HealthChecker ── HTTP GET ─────┼──┐     └──────────────────────────┘
│        ├─ UptimeTracker (state file)     │  └────► https://app.example/up
│        └─ Renderer → soloterm/screen     │
└──────────────────────────────────────────┘
```

Honeybee is a standalone CLI application. It is not a package installed inside the monitored app. It runs on the machine attached to the monitor and reaches the app server over SSH. One instance monitors one application.

## Implemented so far

| File | What it does |
|---|---|
| `bin/honeybee` | Executable entry point. Loads Composer's autoloader (`$_composer_autoload_path` when started through Composer's bin proxy, as with a global or dependency install; otherwise `vendor/autoload.php` next to it) and exits with `Cli::run()`'s return value. |
| `src/Cli.php` | Interprets the arguments: `--version`/`-V` prints `Honeybee <version>`, `--help`/`-h` prints usage, an unknown option or more than one argument is a usage error. With no arguments it currently reports that the dashboard is not implemented yet; F12 replaces that with starting `Application`. Output goes to injected streams, so it is tested in-process. |
| `src/ExitCode.php` | Int-backed enum of process exit codes: `Success` (0), `Failure` (1), `Usage` (2). |
| `src/Honeybee.php` | Typed constants `NAME` and `VERSION`, the single source of the version number. |

The package is `type: project` with `bin/honeybee` in Composer's `bin`, so it also works with `composer create-project` or `composer global require` once it is published.

## Planned namespaces

All code lives under `Basileaelterman\Honeybee\` in `src/`.

| Namespace | Responsibility |
|---|---|
| `Config` | Load and validate Honeybee's own `.env` into a readonly `Config` object |
| `Ssh` | Run remote commands through the system `ssh` binary; non-blocking process handles |
| `Framework` | `FrameworkAdapter` interface plus one sub-namespace per framework (`Framework\Laravel`) |
| `Storage` | Storage scans, output parsing, `FileCategory` enum, storage value objects |
| `Health` | HTTP health check, `HealthStatus`, `UptimeTracker` |
| `Tui` | Terminal control (alternate screen, raw mode, size, signals), layout and rendering |
| (root) | `Application`: wires everything together and owns the main loop |

Value objects (for example `AppInfo`, `DiskDefinition`, `DiskUsage`, `CategoryUsage`, `StorageSnapshot`, `HealthStatus`) are `final readonly` classes. They carry data between collectors and the renderer, so the renderer never touches SSH or framework code.

## Framework abstraction

```php
interface FrameworkAdapter
{
    // Remote command that prints JSON describing the app (name, url, disks).
    // Parse that JSON into an AppInfo with DiskDefinition[].
    // Default health URL for this framework.
}
```

Adding a framework means adding one adapter and registering it in the `HONEYBEE_FRAMEWORK` → adapter `match`. The SSH layer, the collectors and the TUI must not change. See [`docs/plans/symfony-support.md`](../plans/symfony-support.md).

## Main loop

`Application::run()` loops until a quit signal arrives:

1. Handle pending signals (`pcntl_async_signals(true)`) and keyboard input (`q` quits).
2. Poll running remote processes (non-blocking pipe reads). When one finishes, parse its output into a new snapshot. On failure, keep the previous snapshot and mark it stale.
3. If `HONEYBEE_REFRESH_INTERVAL` has passed and no scan is running, start a new scan and a health check.
4. Render the current state.
5. Sleep until the next tick (about 1 second).

The loop must never block on remote work. A slow `find` on a big disk must not freeze the uptime clock or the screen.

## Resilience (24/7)

- No exception from collection or parsing may escape the loop. Catch it at the loop boundary, show it in the status bar and retry on the next interval.
- After an SSH failure, discovery runs again on the next successful connection.
- The terminal is restored on every exit path: normal quit, SIGINT, SIGTERM, uncaught error (`register_shutdown_function`).
- Memory must stay flat over weeks. Keep only the current and previous snapshot, never a growing history.

## Dependencies

| Package | Why |
|---|---|
| `soloterm/screen` | Virtual terminal buffer with differential output, so only changed lines are redrawn (no flicker). Actively maintained. Chosen over `php-tui/php-tui`, whose last release was in 2024. |
| `vlucas/phpdotenv` | Parsing `.env` correctly (quotes, escaping). `parse_ini_file` does not handle the `.env` format reliably. |

Dev:

| Package | Why |
|---|---|
| `phpunit/phpunit` | Test runner (`composer test`). |
| `phpstan/phpstan` | Static analysis at level max (`composer analyse`). |
| `friendsofphp/php-cs-fixer` | PER-CS 2.0 formatting and checking (`composer format`, `composer lint`). |

Required PHP extensions: `mbstring` (needed by `soloterm/screen`), `pcntl` (signals and SIGWINCH) and `curl` (non-blocking health check with `curl_multi_*`, see the roadmap decision). All three are listed in `composer.json`, so `composer install` refuses to run without them.
