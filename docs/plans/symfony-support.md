# Symfony support

Add `Framework\Symfony\SymfonyAdapter` implementing `FrameworkAdapter`.

## Open questions

- **Storage locations.** Symfony has no single filesystem config. Candidates:
  - Flysystem bundle (`league/flysystem-bundle`) storages from `config/packages/flysystem.yaml`, read through `bin/console debug:config flysystem --format=json` (Symfony 6.3+) so the bundle's own config handling is used.
  - VichUploaderBundle mappings (`upload_destination`).
  - Fallback: a list of directories set in Honeybee's `.env`.
- **App name.** Symfony has no `app.name`. Use the project directory name, or a `HONEYBEE_APP_NAME` override.
- **Health URL.** There is no built-in `/up` route, so `HONEYBEE_HEALTH_URL` would be required for Symfony.

Nothing outside the adapter should need to change. If something does, the abstraction is wrong and should be fixed first.
