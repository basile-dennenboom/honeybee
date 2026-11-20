# Data collection

> Status: design. Nothing here is implemented yet.

## SSH transport

Remote commands run through the system OpenSSH client, started with `proc_open()` and an **argument array**, so no local shell parses the command:

```
ssh -o BatchMode=yes
    -o ConnectTimeout=10
    -o ServerAliveInterval=15
    -o ControlMaster=auto
    -o ControlPath=<sys_get_temp_dir()>/honeybee-%C
    -o ControlPersist=120
    [-p PORT] [-l USER] [-i IDENTITY_FILE]
    HOST  '<remote command>'
```

- `BatchMode=yes`: SSH fails instead of prompting. A prompt would break the TUI.
- `ControlMaster`/`ControlPersist`: every refresh reuses one authenticated connection.
- The remote command is a single string that the **remote** shell parses. Every value put into it (paths, PHP binary) must go through `escapeshellarg()`.
- Pipes are set non-blocking (`stream_set_blocking(false)`), and the main loop polls them. A process that runs longer than its timeout is killed (`proc_terminate`) and counts as a failure.

Keep the SSH layer behind an interface (e.g. `RemoteShell`), so collectors can be tested with recorded output and no server.

## Discovery (framework adapter)

Runs at startup and after each reconnect. For Laravel:

```
cd <APP_PATH> && <REMOTE_PHP> -r '<bootstrap snippet>'
```

The snippet requires `vendor/autoload.php`, boots the console kernel from `bootstrap/app.php` and prints JSON:

```json
{
  "name": "My App",
  "url": "https://myapp.example",
  "disks": {
    "local":  {"driver": "local", "root": "/var/www/myapp/storage/app/private"},
    "public": {"driver": "local", "root": "/var/www/myapp/storage/app/public"},
    "s3":     {"driver": "s3",    "root": null}
  }
}
```

**Security:** the snippet must select only `driver` and `root` from each disk *on the server*. Never send the full `config('filesystems.disks')`, because it contains credentials (S3 keys, FTP passwords).

Disks with a driver other than `local` are kept in the list but marked "not measured". See [`docs/plans/cloud-disks.md`](../plans/cloud-disks.md).

## Storage scan

Runs every `HONEYBEE_REFRESH_INTERVAL` seconds, and never while a previous scan is still running. Each local disk is scanned with one remote command that bundles:

1. **Capacity:** `df -Pk <root>`. POSIX output in 1K blocks: filesystem, total, used, available, mount point. Disks whose roots share a filesystem share the same capacity numbers; the header shows this instead of adding them up twice.
2. **Usage per extension:** `nice -n 19 find <root> -type f [prune nested roots] -printf '%s\t%f\n' | awk …`. Aggregating on the server keeps the output tiny: one line per extension, `ext<TAB>bytes<TAB>count`. Extensions are lowercased, and files without an extension get an empty key.

### Nested disk roots

In Laravel ≤ 10 the `local` root (`storage/app`) contains the `public` root (`storage/app/public`). Before building a disk's `find`, check every other local disk root. If one is inside this disk's root, prune it (`-path <nested> -prune -o … -print`) so its files count only toward their own disk.

### Classification

`FileCategory` is a backed enum. Each case has a label, an ANSI color and its extension list. Files are classified by extension only, because detecting MIME types remotely would mean reading every file:

| Category | Example extensions |
|---|---|
| Images | jpg, jpeg, png, gif, webp, svg, avif, heic, bmp, tiff, ico |
| Documents | pdf, doc(x), xls(x), ppt(x), odt, ods, txt, csv, rtf, md |
| Video | mp4, mov, avi, mkv, webm, m4v |
| Audio | mp3, wav, ogg, flac, m4a, aac |
| Archives | zip, tar, gz, tgz, rar, 7z, bz2 |
| Logs | log |
| Other | everything else, including no extension |

The extension → category mapping happens locally, after the scan output is parsed.

## Health check

From the **monitor machine**: an HTTP GET to `HONEYBEE_HEALTH_URL` (default: the discovered app `url` + `/up`, Laravel's built-in health route). It uses PHP's HTTP stream wrapper with a `HONEYBEE_HEALTH_TIMEOUT` timeout and `ignore_errors` so the status code can be read. A 2xx response means up; anything else, including a timeout or DNS failure, means down.

The check runs once per refresh interval. Its timeout is short so a hanging server cannot stall the main loop for long.

## Uptime

`UptimeTracker` records the moment Honeybee sees the app go from down (or unknown) to up. Uptime is `now - upSince`, shown as days, hours and minutes. `upSince` is saved to a small JSON state file in the Honeybee directory, so restarting Honeybee does not reset it. While the app is down, the status bar shows how long it has been down instead.
