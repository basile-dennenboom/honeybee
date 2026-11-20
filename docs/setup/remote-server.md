# Remote server

Honeybee installs nothing on the application server. It connects over SSH and runs standard commands there.

## Server requirements

- A Linux server with **GNU** `find` (needs `-printf`), `awk` and `df`
- The PHP CLI (the same PHP the application runs on), so Honeybee can read the app's configuration
- A deployed Laravel application, with `vendor/` installed, at the path set in `HONEYBEE_APP_PATH`

## SSH access

Honeybee uses your local OpenSSH client in non-interactive mode (`BatchMode=yes`). Because it can never answer a password or passphrase prompt, authentication must work without typing anything:

- Use an SSH key, loaded in your `ssh-agent` or without a passphrase.
- The server's host key must already be in `~/.ssh/known_hosts`. Connect once by hand to accept it.

Test it with:

```bash
ssh -o BatchMode=yes <host> 'cd /path/to/app && php -v'
```

If that prints the PHP version without asking for anything, Honeybee can connect.

The simplest setup is an entry in `~/.ssh/config`, which you then reference with `HONEYBEE_SSH_HOST`:

```
Host myapp-production
    HostName 203.0.113.10
    User deploy
    IdentityFile ~/.ssh/id_ed25519
```

## Permissions

The SSH user needs:

- read access to the application directory (to boot the app's config), and
- read and execute access to every local disk root (for example `storage/app`) so the files can be listed and measured.

A read-only deploy user is enough. Honeybee never writes on the server.

## What runs on the server

| When | Command | Purpose |
|---|---|---|
| Startup and after each reconnect | `php -r …` in the app directory | Read the app name and each filesystem disk's driver and root path. Credentials are filtered out on the server and never sent. |
| Every refresh | `df -Pk <root>` | Capacity and free space of the partition holding each disk |
| Every refresh | `nice find <root> -type f -printf … \| awk …` | Total size and file count per file extension |

Scanning a very large disk (millions of files) takes server I/O. Scans run at low priority, and a new scan never starts while the previous one is still running. If scans are too heavy, raise `HONEYBEE_REFRESH_INTERVAL`.
