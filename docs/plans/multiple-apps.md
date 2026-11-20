# Multiple applications

v1 monitors one application per Honeybee instance; for several apps, run several instances.

## Ideas

- A config file listing several apps (each with its own SSH host, path and framework).
- Display options: rotate between apps every N seconds, or a compact grid with one summary panel per app when the screen is large enough.
- One SSH ControlMaster connection per host, shared by apps on the same server.
