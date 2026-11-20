# Configuration

Honeybee reads its settings from a `.env` file in the Honeybee project root. This is **not** the application's `.env`. Each Honeybee instance monitors exactly one application; to monitor several, run several instances, each with its own `.env`.

[`.env.example`](../../.env.example) lists every variable with its default. Copy it to `.env` and fill in at least the two required values. Keep the two files in sync.

Only the `.env` file is read. Environment variables set in your shell (or by systemd, etc.) are ignored. An empty value, including `KEY=` followed only by a `# comment`, counts as not set, so the default applies.

## Connection

| Variable | Required | Default | Description |
|---|---|---|---|
| `HONEYBEE_SSH_HOST` | yes | | Hostname, IP or `~/.ssh/config` alias of the application server |
| `HONEYBEE_SSH_USER` | no | from ssh config | SSH user |
| `HONEYBEE_SSH_PORT` | no | from ssh config / `22` | SSH port, a whole number from 1 to 65535 |
| `HONEYBEE_SSH_IDENTITY_FILE` | no | from ssh config / agent | Path to the private key to use |
| `HONEYBEE_APP_PATH` | yes | | Absolute path of the application on the server, starting with `/`, e.g. `/var/www/myapp/current` |
| `HONEYBEE_REMOTE_PHP` | no | `php` | PHP binary on the server, e.g. `php8.5` |

## Application

| Variable | Required | Default | Description |
|---|---|---|---|
| `HONEYBEE_FRAMEWORK` | no | `laravel` | Framework of the application (case-insensitive). Only `laravel` is supported for now. |
| `HONEYBEE_HEALTH_URL` | no | the app's `APP_URL` + `/up` | URL checked from the monitor machine. Must be an `http://` or `https://` URL. Any 2xx response counts as "up". |
| `HONEYBEE_HEALTH_TIMEOUT` | no | `5` | Seconds before a health check counts as failed (positive whole number) |

## Display

| Variable | Required | Default | Description |
|---|---|---|---|
| `HONEYBEE_REFRESH_INTERVAL` | no | `30` | Seconds between storage scans and health checks (positive whole number) |

## Example

```dotenv
HONEYBEE_SSH_HOST=myapp-production
HONEYBEE_APP_PATH=/var/www/myapp/current
HONEYBEE_REFRESH_INTERVAL=30
```

## Configuration errors

Honeybee checks the whole `.env` file at startup, before it takes over the terminal. If anything is wrong, it lists every problem at once and exits with status 1:

```text
Honeybee: configuration error in /opt/honeybee/.env:
  - HONEYBEE_SSH_HOST is required: the hostname, IP or ~/.ssh/config alias of the application server.
  - HONEYBEE_REFRESH_INTERVAL must be a positive whole number, got "often".
```

A missing `.env` file is reported the same way.
