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

- **PHP-CS-Fixer** with the `@PER-CS` rule set. `no_superfluous_phpdoc_tags` and `phpdoc_to_comment` must be disabled, or the fixer removes the required docblock tags.
- **PHPStan**, as high a level as is practical (aim for `max`).
- **PHPUnit** for tests. Collectors are tested against recorded command output (fixtures), never against a real server.
