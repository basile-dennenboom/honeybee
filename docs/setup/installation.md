# Installation

> The dashboard is not implemented yet. Installing and `bin/honeybee --version` already work; running the dashboard does not.

## Requirements (monitor machine)

- PHP 8.5 or newer (CLI), with these extensions:
  - `mbstring` (needed by the terminal renderer)
  - `pcntl` (signal handling: clean exit and terminal resize)
  - `curl` (health check of the application)
- Composer
- An OpenSSH client (`ssh`), with key-based access to the application server (see [Remote server](remote-server.md))
- A terminal with 256-color or true-color support (iTerm2, Ghostty, GNOME Terminal, Windows Terminal through WSL, …)

Windows is only supported through WSL.

## Install

```bash
git clone <repository-url> honeybee
cd honeybee
composer install
cp .env.example .env   # then edit it, see configuration.md
bin/honeybee --version # check the install: prints "Honeybee <version>"
```

`composer install` stops with an error if PHP is older than 8.5 or one of the extensions above is missing.

## Run

```bash
bin/honeybee
```

Honeybee takes over the whole terminal window. Press `q` or `Ctrl+C` to quit. Your terminal is restored when it exits.

To keep it on a monitor, open a terminal on that screen, make it full screen and start Honeybee. If Honeybee loses the connection to the server it does not exit; it keeps showing the last known data and retries.
