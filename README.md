# Honeybee

A full-screen terminal dashboard that shows an application's storage usage, live. It is made to sit on an office monitor and run 24/7.

Honeybee runs on your own machine and reads the application's server over SSH. Nothing is installed on that server. For each filesystem disk of the application, it shows the partition's capacity and how the stored files split into categories (images, documents, video, …), each in its own color. A status bar that is always visible shows the application's name, its health (up or down) and its uptime. Data refreshes every 30 seconds by default.

Supported frameworks: **Laravel**. Other frameworks, such as Symfony, are planned.

> **Status:** early development. The design is done; the implementation has not started yet.

## Setup

1. [Installation](docs/setup/installation.md): requirements and installing Honeybee on the monitor machine.
2. [Remote server](docs/setup/remote-server.md): what the application server needs and how to set up SSH access.
3. [Configuration](docs/setup/configuration.md): every `.env` setting.

## Documentation

- [Internal documentation](docs/internal/README.md): architecture and conventions for contributors.
- [Plans](docs/plans/README.md): ideas and features for the future.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
