<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Tests\Unit;

use Basileaelterman\Honeybee\Cli;
use Basileaelterman\Honeybee\Enums\ExitCode;
use Basileaelterman\Honeybee\Honeybee;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Cli::class)]
final class CliTest extends TestCase
{
    /**
     * Provide the short and long spelling of the version option.
     *
     * @return array<string, array{string}>
     */
    public static function versionOptionProvider(): array
    {
        return [
            'long' => ['--version'],
            'short' => ['-V'],
        ];
    }

    /**
     * Provide the short and long spelling of the help option.
     *
     * @return array<string, array{string}>
     */
    public static function helpOptionProvider(): array
    {
        return [
            'long' => ['--help'],
            'short' => ['-h'],
        ];
    }

    /**
     * The version option prints the name and version on stdout and succeeds.
     *
     * @param string $option The version option to pass.
     *
     * @return void
     */
    #[DataProvider('versionOptionProvider')]
    public function testVersionOptionPrintsVersion(string $option): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli([$option]);

        self::assertSame(ExitCode::Success->value, $exitCode);
        self::assertSame('Honeybee ' . Honeybee::VERSION . "\n", $stdout);
        self::assertSame('', $stderr);
    }

    /**
     * The help option prints usage on stdout and succeeds.
     *
     * @param string $option The help option to pass.
     *
     * @return void
     */
    #[DataProvider('helpOptionProvider')]
    public function testHelpOptionPrintsUsage(string $option): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli([$option]);

        self::assertSame(ExitCode::Success->value, $exitCode);
        self::assertStringStartsWith('Usage: honeybee', $stdout);
        self::assertStringContainsString('--version', $stdout);
        self::assertSame('', $stderr);
    }

    /**
     * Without arguments the command reports that the dashboard is not available yet.
     *
     * @return void
     */
    public function testNoArgumentsFailsWithMessage(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli([]);

        self::assertSame(ExitCode::Failure->value, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('not implemented yet', $stderr);
    }

    /**
     * An unknown option is a usage error that names the option.
     *
     * @return void
     */
    public function testUnknownOptionIsUsageError(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli(['--bogus']);

        self::assertSame(ExitCode::Usage->value, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString("unknown option '--bogus'", $stderr);
    }

    /**
     * More than one argument is a usage error.
     *
     * @return void
     */
    public function testMultipleArgumentsIsUsageError(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli(['--version', '--help']);

        self::assertSame(ExitCode::Usage->value, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('at most one option', $stderr);
    }

    /**
     * Run the CLI in-process with in-memory output streams.
     *
     * @param list<string> $arguments Arguments to pass, without the script name.
     *
     * @return array{int, string, string} Exit code, stdout contents and stderr contents.
     *
     * @throws RuntimeException When a memory stream cannot be opened or read.
     */
    private function runCli(array $arguments): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');

        if ($stdout === false || $stderr === false) {
            throw new RuntimeException('Could not open memory streams.');
        }

        $exitCode = new Cli()->run($arguments, $stdout, $stderr);

        $output = [$exitCode, $this->readStream($stdout), $this->readStream($stderr)];

        fclose($stdout);
        fclose($stderr);

        return $output;
    }

    /**
     * Read everything written to a stream so far.
     *
     * @param resource $stream The stream to read from the start.
     *
     * @return string The stream contents.
     *
     * @throws RuntimeException When the stream cannot be read.
     */
    private function readStream($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);

        if ($contents === false) {
            throw new RuntimeException('Could not read memory stream.');
        }

        return $contents;
    }
}
