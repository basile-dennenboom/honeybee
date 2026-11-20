# Rendering

> Status: design. Nothing here is implemented yet. Check the exact `soloterm/screen` API against the installed version when implementing.

## Terminal control

A `Terminal` class owns the real terminal:

- **Enter:** alternate screen (`ESC[?1049h`), hide cursor (`ESC[?25l`), and `stty -echo -icanon` so single keypresses (`q`) can be read from a non-blocking STDIN without echo. Save the original `stty -g` output first.
- **Size:** read with `stty size`. Re-read on `SIGWINCH` (`pcntl_signal`), then do a full redraw.
- **Restore:** show the cursor, leave the alternate screen and restore the saved `stty` settings. This must run on quit, SIGINT, SIGTERM and from a `register_shutdown_function` handler, and must be safe to call more than once.

## soloterm/screen

`Soloterm\Screen\Screen` is a virtual terminal buffer, not a widget framework. Honeybee writes text and ANSI sequences into it, and `output()` returns the rendered result. Called with the last rendered sequence number, it returns only the lines that changed. Since most of the screen stays the same between ticks (only the clock and, every 30s, the numbers change), this avoids flicker and keeps the redraw cheap.

When the terminal size changes, create a new `Screen` with the new dimensions and do a full redraw.

Layout, bars and colors are Honeybee's own code. Keep them in small, plain functions that return strings for a given width.

## Layout

Everything must fit on one screen; nothing scrolls. Planned layout:

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ ● My App                      UP  uptime 12d 4h 37m      updated 10:42:13    │ ← status bar
├──────────────────────────────────────────────────────────────────────────────┤
│ local  /var/www/myapp/storage/app/private      /dev/sda1  62.4 / 100.0 GB    │ ← disk header
│ ██████████████████████████████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  │   partition bar
│ ■ Images     18.2 GB  41%  ████████████████                12,402 files      │ ← categories
│ ■ Documents   9.7 GB  22%  █████████                        3,118 files      │
│ ■ Video      14.1 GB  32%  █████████████                       88 files      │
│ ■ Other       2.0 GB   5%  ██                                  731 files      │
├──────────────────────────────────────────────────────────────────────────────┤
│ public  /var/www/myapp/storage/app/public      /dev/sda1  (same partition)   │
│ …                                                                            │
├──────────────────────────────────────────────────────────────────────────────┤
│ s3  driver: s3 — not measured                                                │
└──────────────────────────────────────────────────────────────────────────────┘
```

- The **status bar** is always visible: app name, health (green UP / red DOWN), uptime (or downtime), the time of the last successful scan, and a "stale" warning when the last scan failed.
- **Disk blocks** split the remaining rows. Within a disk, categories are sorted by size, largest first.
- **Shrinking:** when rows run out, first merge the smallest categories into "Other", then drop the per-category bars, then show one summary line per disk. Never print past the last row.
- Sizes use binary units (KiB, MiB, GiB) or decimal ones. Pick one and use it everywhere. Decide this when implementing and record the choice here.

## Colors

Each `FileCategory` case defines its own color, and the category's square, bar and label use it. Health uses green (up), red (down) and yellow (unknown or stale). Use 256-color ANSI codes so most terminals show the same colors.
