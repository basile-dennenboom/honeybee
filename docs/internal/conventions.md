# Conventions

## Standards

- PSR-1 (basic coding standard), PSR-4 (autoloading: `Basileaelterman\Honeybee\` → `src/`, tests in `tests/` under `Basileaelterman\Honeybee\Tests\`), and PER Coding Style 2.0 (formatting; the successor to PSR-12).
- `declare(strict_types=1);` in every PHP file.
- Latest stable PHP. Use the language: enums instead of class constants for fixed sets, `readonly` value objects, constructor property promotion, `match`, named arguments where they make a call clearer.
- Prefer the standard library over writing helpers or adding packages.
- Simple and explicit over clever. No magic methods, no dynamic properties, no hidden global state.

## Docblocks

Every function and method has a docblock with:

- a one-line summary of what it does or returns,
- `@param` for every parameter,
- `@return` (including `void`),
- `@throws` for every exception it can throw, directly or from code it calls without catching.

Do this even when native type declarations already state the types. Use the docblock to add precision where it helps, e.g. `@param list<DiskDefinition> $disks` or `@return array<string, int>`.

```php
/**
 * Parse the per-extension output of a remote storage scan.
 *
 * @param string $output Raw scan output, one "ext<TAB>bytes<TAB>count" line per extension.
 *
 * @return list<CategoryUsage> Usage per category, sorted by size descending.
 *
 * @throws InvalidScanOutputException When a line does not have the expected format.
 */
public function parse(string $output): array
```

## Tooling

| Tool | Config file | Composer script | Notes |
|---|---|---|---|
| PHPUnit 13 | `phpunit.xml.dist` | `composer test` | Suites `Unit` and `Integration`. Fails on warnings, notices, deprecations (PHP and PHPUnit) and risky tests. Cache in `.phpunit.cache/`. |
| PHPStan 2 | `phpstan.neon.dist` | `composer analyse` | Level `max` over `bin/honeybee`, `src/` and `tests/`. |
| PHP-CS-Fixer 3 | `.php-cs-fixer.dist.php` | `composer lint` (check only) / `composer format` (apply) | `@PER-CS2x0` (PER-CS 2.0, pinned so updates never change the style) plus `declare_strict_types`. `no_superfluous_phpdoc_tags` and `phpdoc_to_comment` are disabled, or the fixer would remove the required docblock tags. Covers `src/`, `tests/`, `bin/honeybee` and the config file itself. |

A local `phpunit.xml`, `phpstan.neon` or `.php-cs-fixer.php` overrides the `.dist` file and is gitignored.

## Tests

- Test namespace `Basileaelterman\Honeybee\Tests\` → `tests/` (`autoload-dev`).
- `tests/Unit/`: pure logic, in-process, no processes, network or filesystem outside memory streams.
- `tests/Integration/`: tests that start real local processes, such as `bin/honeybee` itself (always `proc_open` with an argument array, no shell).
- Every test class has a `#[CoversClass]` attribute. Test methods get docblocks too.
- Collectors are tested against recorded command output (fixtures), never against a real server.

## Versioning

`Honeybee::VERSION` (`src/Honeybee.php`) is the only place the version number lives; `bin/honeybee --version` prints it. Use semantic versioning and bump the constant before tagging a release (`vX.Y.Z`). `tests/Integration/VersionCommandTest.php` checks that the binary prints the constant, that it is valid semver, and, when HEAD is on a git tag, that the tag matches it.
