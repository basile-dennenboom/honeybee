# Roadmap: Honeybee v1

All features needed for v1: what each one contains, when it is done, and what it depends on. The suggested implementation order is at the end.

Decisions made while planning:

- The health check is **non-blocking**, using PHP's `curl_multi_*` functions. This adds `ext-curl` as a requirement.
- Sizes are shown in **decimal units** (KB, MB, GB; 1 GB = 1000³ bytes).

## Global definition of done (applies to every feature)

- Code follows PSR-1/PSR-4/PER-CS 2.0 and uses `strict_types`. Every function has a full docblock (`@param`, `@return`, `@throws`).
- `composer test`, `composer analyse` and `composer lint` pass.
- Unit tests cover the feature's logic. Remote behavior is tested with recorded fixtures, never against a live server.
- The matching `docs/internal/` document describes what was built (not just the design). User-facing changes are also reflected in `docs/setup/`, and in `.env.example` and `configuration.md` when env vars change.
- Nothing is committed, staged or pushed by Claude.

## F1: Project scaffolding and tooling

- **Contains:** `composer.json` (`php ^8.5`, `ext-mbstring`, `ext-pcntl`, `ext-curl`, `soloterm/screen`, `vlucas/phpdotenv`; dev: PHPUnit, PHPStan, PHP-CS-Fixer; `autoload-dev` for `tests/`), `phpunit.xml.dist`, `phpstan.neon.dist`, `.php-cs-fixer.dist.php` (`@PER-CS`, with `no_superfluous_phpdoc_tags` and `phpdoc_to_comment` disabled), composer scripts `test`/`analyse`/`lint`/`format`, an executable `bin/honeybee` stub, and the `tests/` folder.
- **DoD:** `composer install` works on a clean checkout. All composer scripts run green. `bin/honeybee --version` prints the version and exits 0. The CLAUDE.md Commands section no longer says "not installed yet". `conventions.md` lists the real config files.
- **Depends on:** none.

## F2: Configuration

- **Contains:** `Config\ConfigLoader` (reads `.env` via phpdotenv), a readonly `Config\Config` object, and `Config\ConfigurationException`. Validates required keys (`HONEYBEE_SSH_HOST`, `HONEYBEE_APP_PATH`), integer and positive values (port, timeout, interval) and the framework name, and applies defaults.
- **DoD:** Tests cover defaults, missing required keys, invalid numbers, unknown framework, and the **inline-comment format of the current `.env.example`** (including empty values followed by a comment). With a bad config, `bin/honeybee` prints a clear error and exits non-zero *before* touching the terminal.
- **Depends on:** F1.

## F3: SSH transport

- **Contains:** a `Ssh\RemoteShell` interface, `Ssh\SshRemoteShell` (builds the `ssh` argument array from Config: BatchMode, ConnectTimeout, ServerAliveInterval, ControlMaster/ControlPath/ControlPersist, optional `-p/-l/-i`) and `Ssh\RemoteProcess` (wraps `proc_open` with an argument array, non-blocking pipes, `poll()`/`isRunning()`, collected stdout/stderr, exit code, timeout with `proc_terminate`). Closes the control socket on exit (`ssh -O exit`). Includes a test double `FakeRemoteShell` under `tests/`.
- **DoD:** Tests cover argument building, `escapeshellarg` of every interpolated value, and `RemoteProcess` against a local command (e.g. `php -r`) for success, non-zero exit, stderr capture and timeout kill. A manual check against a real server is written up in `data-collection.md`.
- **Depends on:** F2.

## F4: Domain model and file categories

- **Contains:** `final readonly` value objects `AppInfo`, `DiskDefinition`, `PartitionUsage`, `CategoryUsage`, `DiskUsage` and `StorageSnapshot`. The `Storage\FileCategory` backed enum has `label()`, `color()`, `extensions()` and `fromExtension()`. Also a byte formatter with decimal units (KB/MB/GB/TB).
- **DoD:** Tests check that classification is case-insensitive and that unknown or empty extensions map to `Other`. Every extension maps to exactly one category. Formatter tests cover the unit boundaries and rounding. The categories table in `data-collection.md` matches the enum.
- **Depends on:** F1.

## F5: Framework adapter and Laravel discovery

- **Contains:** a `Framework\FrameworkAdapter` interface (discovery command, discovery-output parser, default health path), `Framework\Laravel\LaravelAdapter` (builds the `cd <path> && <php> -r '<snippet>'` command; the snippet selects only `name`, `url` and each disk's `driver`/`root` on the server) and `Framework\FrameworkAdapterFactory` (`match` on `HONEYBEE_FRAMEWORK`). Errors become a `DiscoveryException`: not a Laravel app, PHP fatal error, invalid JSON.
- **DoD:** Tests parse recorded discovery JSON from Laravel ≤10 (nested `public` root) and from Laravel 11+ (`storage/app/private`). A test asserts the snippet never outputs disk keys other than `driver` and `root`. Error fixtures produce clear messages. Verified by hand against a real Laravel app.
- **Depends on:** F3, F4.

## F6: Storage scanner

- **Contains:** `Storage\StorageScanner`, which builds one remote command per local disk: `df -Pk` plus `nice -n 19 find … -printf | awk` aggregating by extension, with every *other* disk root nested inside this one pruned. `Storage\ScanOutputParser` turns the output into `DiskUsage` and classifies extensions into `CategoryUsage` via `FileCategory`. Disks sharing a partition are detected. Non-local drivers are marked "not measured". A root that does not exist yet shows as empty. Permission errors give partial results plus a warning, not a failure.
- **DoD:** Fixture tests cover df parsing, aggregation and classification, nested-root pruning (asserted on the built command), a shared partition, a missing root, partial output after a permission error, and garbage output (→ exception). A manual run against a real server gives totals that match `du -sk` on the same roots.
- **Depends on:** F3, F4, F5.

## F7: Health check and uptime

- **Contains:** `Health\HealthChecker`, a non-blocking HTTP GET via `curl_multi_*` (`start()`/`poll()`, timeout from config; any 2xx means up). The URL is `HONEYBEE_HEALTH_URL` or the discovered `app.url` plus the adapter's default path. Also `Health\HealthState` (enum: Up/Down/Unknown), a `Health\HealthStatus` value object (state, checkedAt, detail) and `Health\UptimeTracker` (up-since/down-since transitions, persisted to a JSON state file in the Honeybee directory, duration formatted as `Xd Yh Zm`).
- **DoD:** Tests cover the transitions (unknown→up, up→down, down→up resets), state-file save and load (including a corrupt or missing file), and duration formatting (0m, minutes only, more than 1 day). The checker is tested against PHP's built-in server (`php -S`) for 200, 500, a timeout and a refused connection. `data-collection.md` and `installation.md` describe curl_multi and `ext-curl`. The state file is gitignored.
- **Depends on:** F2, F4. F5 supplies the default URL; the checker itself only needs a URL.

## F8: Terminal control

- **Contains:** `Tui\Terminal`. It enters the alternate screen, hides the cursor and saves then sets `stty -echo -icanon`. It reads the size via `stty size`, reads keys from non-blocking STDIN, and handles SIGWINCH (resize flag) plus SIGINT/SIGTERM (quit flag) via `pcntl_async_signals`. `restore()` is idempotent and is also registered with `register_shutdown_function`.
- **DoD:** A manual checklist in `rendering.md` passes, and after each exit the terminal is fully usable (echo on, cursor visible, main screen back): quit with `q`, `Ctrl+C`, `kill -TERM`, a forced fatal error, and resizing the window. Unit tests cover the escape-sequence output.
- **Depends on:** F1.

## F9: Rendering engine and layout

- **Contains:** `Tui\Renderer`, which writes a frame into a `Soloterm\Screen\Screen` and outputs only the changed lines. A new Screen and a full redraw happen on resize. Also primitives (`Tui\Ansi` colors/styles, a horizontal bar, padding and truncation by `mb_strwidth`) and a `Tui\ViewState` (everything the screen shows, built by the app loop). The layout allocates rows: the status bar is fixed and the disk blocks share the rest.
- **DoD:** Tests render fixture states at 80×24, 120×40, 200×60 and 40×10 and check that no line is wider than the terminal and no frame is taller (measured on text with ANSI stripped). Repeated renders of an unchanged state produce empty diff output. The decimal-units decision and the real `soloterm/screen` usage are recorded in `rendering.md`.
- **Depends on:** F4, F8.

## F10: Status bar

- **Contains:** the always-visible bar showing app name (or "connecting…"), health (green UP, red DOWN, yellow UNKNOWN), uptime or downtime (`Xd Yh Zm`), the time of the last successful scan, and a yellow "stale since HH:MM" or short error text when the last scan failed.
- **DoD:** Tests render every combination: up, down, unknown, stale, error, no app info yet, and a long app name truncated at narrow widths. It fits on one row at 40 columns and wider.
- **Depends on:** F7, F9.

## F11: Storage panels

- **Contains:** one block per disk. A header shows the name, root, filesystem and used/total; a partition bar comes next; then the categories, sorted by size, each with its colored square, size, percentage, bar and file count. Disks that share a partition say so instead of repeating capacity. Non-local disks get a one-line "driver: s3 — not measured". The shrink strategy merges the smallest categories into Other, then drops the per-category bars, then falls back to one summary line per disk.
- **DoD:** Tests check each shrink step is triggered at the right heights and that nothing is cut mid-block. Percentages add up to 100% (±1 for rounding). Every category color matches the enum. A screenshot or ASCII capture of a real render is added to `rendering.md`.
- **Depends on:** F6 (snapshot shape), F9.

## F12: Application loop and orchestration

- **Contains:** `Application` and the wiring in `bin/honeybee`. The startup order is: load config, enter the terminal, start discovery. A ~1s tick polls SSH processes and curl_multi, handles keys and signals, starts a scan plus health check when the interval has passed (never while one is running), builds the `ViewState` and renders. Discovery runs again after an SSH failure. Exceptions are caught at the loop boundary, keeping the last snapshot and marking it stale. Only the current and previous snapshots are kept, so memory stays flat.
- **DoD:** With the time source injected and the fake shell, tests cover the scheduling (no overlapping scans, interval honored after a slow scan), rediscovery after a failure, and stale marking. Manually, against a real server, the whole screen works end to end. Pulling the network shows stale data plus DOWN without crashing, and restoring it recovers on its own.
- **Depends on:** F2–F11.

## F13: 24/7 hardening and release readiness

- **Contains:** a soak test of 24h or more against a real app; checks that memory and file descriptors stay flat (no leaked `proc_open` or curl handles) and that ControlMaster recovers after the server reboots. The README status line is updated, `docs/setup/*` is checked against the real behavior, and `.env.example` and `configuration.md` are confirmed to match `ConfigLoader`.
- **DoD:** After 24h, `memory_get_usage()` and the open-FD count (checked with `lsof`) are within a small fixed bound of the values after the first hour. A fresh clone set up by following only `docs/setup/` reaches a working screen. The README no longer says "not implemented".
- **Depends on:** F12.

## Suggested implementation order

1. **F1** Scaffolding and tooling
2. **F2** Configuration
3. **F4** Domain model and file categories (pure PHP, unblocks everything downstream)
4. **F3** SSH transport
5. **F5** Laravel discovery (first real end-to-end data from a server)
6. **F6** Storage scanner
7. **F7** Health check and uptime
8. **F8** Terminal control
9. **F9** Rendering engine and layout
10. **F10** Status bar
11. **F11** Storage panels
12. **F12** Application loop and orchestration (first full running dashboard)
13. **F13** 24/7 hardening and release readiness

Reasoning: build the pure, testable foundations first (F1, F2, F4). Prove the data path against a real server next (F3, F5, F6, F7), before any UI exists, because that is where the unknowns are: GNU `find` behavior, permissions, nested roots. The UI layers (F8–F11) then render real-shaped data, and F12 wires it all together. F7 and F8/F9 are independent of each other and can swap places.

## Later (not in v1)

- An error log file (`HONEYBEE_LOG_FILE`) with timestamped SSH, parse and health errors
- An `r` key to force an immediate refresh
- How long the last scan took, shown in the status bar
- Growth trend per disk or category compared with 24h ago (needs a history file)
- Existing plans: [Symfony support](symfony-support.md), [Cloud disks](cloud-disks.md), [Multiple apps](multiple-apps.md)
