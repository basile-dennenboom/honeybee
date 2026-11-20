<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Tests\Integration;

use Basileaelterman\Honeybee\Cli\Cli;
use Basileaelterman\Honeybee\Config\Config;
use Basileaelterman\Honeybee\Config\ConfigException;
use Basileaelterman\Honeybee\Config\ConfigLoader;
use Basileaelterman\Honeybee\Enums\ExitCode;
use Basileaelterman\Honeybee\Enums\Framework;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Checks how starting the dashboard reacts to the .env file in the config directory.
 */
#[CoversClass(Cli::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigException::class)]
#[UsesClass(Config::class)]
#[UsesClass(Framework::class)]
final class ConfigCommandTest extends TestCase
{
    use UsesTemporaryDirectory;

    /**
     * A bad .env makes the command list every problem on stderr and exit non-zero, without terminal output.
     *
     * @return void
     *
     * @throws RuntimeException When the .env file cannot be written or the streams cannot be used.
     */
    public function testBadConfigPrintsErrorsAndFails(): void
    {
        $this->writeEnvFile("HONEYBEE_SSH_HOST=\nHONEYBEE_APP_PATH=/var/www/myapp\nHONEYBEE_REFRESH_INTERVAL=often\n");

        [$exitCode, $stdout, $stderr] = $this->runCli();

        self::assertSame(ExitCode::Failure->value, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringStartsWith("Honeybee: configuration error in {$this->directory}/.env:\n", $stderr);
        self::assertStringContainsString("  - HONEYBEE_SSH_HOST is required", $stderr);
        self::assertStringContainsString('  - HONEYBEE_REFRESH_INTERVAL must be a positive whole number, got "often".', $stderr);
        self::assertStringNotContainsString("\e", $stderr, 'No escape sequences may be written before the config is valid.');
    }

    /**
     * A valid .env gets past configuration and reaches the not-yet-implemented dashboard.
     *
     * @return void
     *
     * @throws RuntimeException When the .env file cannot be written or the streams cannot be used.
     */
    public function testValidConfigReachesDashboard(): void
    {
        $this->writeEnvFile("HONEYBEE_SSH_HOST=myapp-production\nHONEYBEE_APP_PATH=/var/www/myapp\n");

        [$exitCode, $stdout, $stderr] = $this->runCli();

        self::assertSame(ExitCode::Failure->value, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('not implemented yet', $stderr);
        self::assertStringNotContainsString('configuration error', $stderr);
    }

    /**
     * Run the CLI without arguments against the temporary directory, with in-memory output streams.
     *
     * @return array{int, string, string} Exit code, stdout contents and stderr contents.
     *
     * @throws RuntimeException When a memory stream cannot be opened or read.
     */
    private function runCli(): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');

        if ($stdout === false || $stderr === false) {
            throw new RuntimeException('Could not open memory streams.');
        }

        $exitCode = new Cli($this->directory)->run([], $stdout, $stderr);

        rewind($stdout);
        rewind($stderr);
        $output = [$exitCode, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];

        fclose($stdout);
        fclose($stderr);

        return $output;
    }
}
