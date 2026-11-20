# Configuration

Honeybee reads its settings from a `.env` file in the Honeybee project root. This is **not** the application's `.env`. Each Honeybee instance monitors exactly one application; to monitor several, run several instances, each with its own `.env`.

> These are the planned variables. [`.env.example`](../../.env.example) lists all of them with their defaults. Keep the two files in sync.

## Connection

| Variable | Required | Default | Description |
|---|---|---|---|
| `HONEYBEE_SSH_HOST` | yes | | Hostname, IP or `~/.ssh/config` alias of the application server |
| `HONEYBEE_SSH_USER` | no | from ssh config | SSH user |
| `HONEYBEE_SSH_PORT` | no | from ssh config / `22` | SSH port |
| `HONEYBEE_SSH_IDENTITY_FILE` | no | from ssh config / agent | Path to the private key to use |
| `HONEYBEE_APP_PATH` | yes | | Absolute path of the application on the server, e.g. `/var/www/myapp/current` |
| `HONEYBEE_REMOTE_PHP` | no | `php` | PHP binary on the server, e.g. `php8.5` |

## Application

| Variable | Required | Default | Description |
|---|---|---|---|
| `HONEYBEE_FRAMEWORK` | no | `laravel` | Framework of the application. Only `laravel` is supported for now. |
| `HONEYBEE_HEALTH_URL` | no | the app's `APP_URL` + `/up` | URL checked from the monitor machine. Any 2xx response counts as "up". |
| `HONEYBEE_HEALTH_TIMEOUT` | no | `5` | Seconds before a health check counts as failed |

## Display

| Variable | Required | Default | Description |
|---|---|---|---|
| `HONEYBEE_REFRESH_INTERVAL` | no | `30` | Seconds between storage scans and health checks |

## Example

```dotenv
HONEYBEE_SSH_HOST=myapp-production
HONEYBEE_APP_PATH=/var/www/myapp/current
HONEYBEE_REFRESH_INTERVAL=30
```
