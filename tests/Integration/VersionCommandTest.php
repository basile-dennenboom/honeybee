<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Tests\Integration;

use Basileaelterman\Honeybee\Cli;
use Basileaelterman\Honeybee\Honeybee;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Checks that the real bin/honeybee executable reports the application's actual version.
 */
#[CoversClass(Cli::class)]
#[CoversClass(Honeybee::class)]
final class VersionCommandTest extends TestCase
{
    private const string PROJECT_ROOT = __DIR__ . '/../..';

    /**
     * Running "bin/honeybee --version" prints exactly the version in Honeybee::VERSION and exits 0.
     *
     * @return void
     *
     * @throws RuntimeException When the process cannot be started.
     */
    public function testBinaryPrintsActualVersion(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runProcess(
            [PHP_BINARY, self::PROJECT_ROOT . '/bin/honeybee', '--version'],
        );

        self::assertSame(0, $exitCode, "bin/honeybee --version failed. stderr: {$stderr}");
        self::assertSame('', $stderr);
        self::assertSame('Honeybee ' . Honeybee::VERSION . "\n", $stdout);
    }

    /**
     * Honeybee::VERSION is a valid semantic version (MAJOR.MINOR.PATCH with optional pre-release).
     *
     * @return void
     */
    public function testVersionIsSemanticVersion(): void
    {
        self::assertMatchesRegularExpression(
            '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(-[0-9A-Za-z.-]+)?$/',
            Honeybee::VERSION,
        );
    }

    /**
     * When HEAD is exactly on a git tag, that tag (without a leading "v") equals Honeybee::VERSION.
     *
     * This catches a release that was tagged without bumping the constant.
     *
     * @return void
     *
     * @throws RuntimeException When the process cannot be started.
     */
    public function testVersionMatchesGitTagOnRelease(): void
    {
        [$exitCode, $stdout] = $this->runProcess(
            ['git', '-C', self::PROJECT_ROOT, 'describe', '--tags', '--exact-match', 'HEAD'],
        );

        if ($exitCode !== 0) {
            self::markTestSkipped('HEAD is not on a git tag (or git is unavailable), so there is no release tag to compare.');
        }

        $tag = trim($stdout);
        $tagVersion = str_starts_with($tag, 'v') ? substr($tag, 1) : $tag;

        self::assertSame($tagVersion, Honeybee::VERSION, "Git tag '{$tag}' does not match Honeybee::VERSION.");
    }

    /**
     * Run a command without a shell and collect its exit code and output.
     *
     * @param list<string> $command Program and arguments.
     *
     * @return array{int, string, string} Exit code, stdout contents and stderr contents.
     *
     * @throws RuntimeException When the process cannot be started.
     */
    private function runProcess(array $command): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if ($process === false) {
            throw new RuntimeException('Could not start process: ' . implode(' ', $command));
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
