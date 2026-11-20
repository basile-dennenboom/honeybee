<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Cli;

use Basileaelterman\Honeybee\Config\ConfigException;
use Basileaelterman\Honeybee\Config\ConfigLoader;
use Basileaelterman\Honeybee\Enums\ExitCode;
use Basileaelterman\Honeybee\Honeybee;

/**
 * Command-line entry point: interprets the arguments given to bin/honeybee.
 */
final class Cli
{
    public function __construct(
        private readonly string $configDirectory,
    ) {}

    /**
     * Run the command for the given arguments and return the process exit code.
     *
     * @param list<string> $arguments Command-line arguments, without the script name.
     * @param resource $stdout Stream for normal output.
     * @param resource $stderr Stream for errors.
     *
     * @return int Exit code for the process.
     */
    public function run(array $arguments, $stdout, $stderr): int
    {
        if ($arguments === []) {
            return $this->startDashboard($stderr)->value;
        }

        if (count($arguments) > 1) {
            fwrite($stderr, Honeybee::NAME . ": expected at most one option. Try '--help'.\n");

            return ExitCode::Usage->value;
        }

        $exitCode = match ($arguments[0]) {
            '--version', '-V' => $this->printVersion($stdout),
            '--help', '-h' => $this->printUsage($stdout),
            default => $this->printUnknownOption($arguments[0], $stderr),
        };

        return $exitCode->value;
    }

    /**
     * Load the configuration and start the dashboard.
     *
     * @param resource $stderr Stream for errors.
     *
     * @return ExitCode ExitCode::Failure, both for a bad configuration and because the dashboard is not implemented yet.
     */
    private function startDashboard($stderr): ExitCode
    {
        try {
            new ConfigLoader()->load($this->configDirectory);
        } catch (ConfigException $exception) {
            fwrite($stderr, Honeybee::NAME . ": configuration error in {$this->configDirectory}/.env:\n");

            foreach ($exception->problems as $problem) {
                fwrite($stderr, "  - {$problem}\n");
            }

            return ExitCode::Failure;
        }

        fwrite($stderr, Honeybee::NAME . ": the dashboard is not implemented yet. Try '--help'.\n");

        return ExitCode::Failure;
    }

    /**
     * Write the name and version.
     *
     * @param resource $stdout Stream for normal output.
     *
     * @return ExitCode Always ExitCode::Success.
     */
    private function printVersion($stdout): ExitCode
    {
        fwrite($stdout, Honeybee::NAME . ' ' . Honeybee::VERSION . "\n");

        return ExitCode::Success;
    }

    /**
     * Write the usage text.
     *
     * @param resource $stdout Stream for normal output.
     *
     * @return ExitCode Always ExitCode::Success.
     */
    private function printUsage($stdout): ExitCode
    {
        fwrite($stdout, <<<'TEXT'
            Usage: honeybee [option]

            Options:
              -h, --help     Show this help and exit.
              -V, --version  Show the version and exit.

            TEXT);

        return ExitCode::Success;
    }

    /**
     * Write an error about an option Honeybee does not know.
     *
     * @param string $option The unknown option as given.
     * @param resource $stderr Stream for errors.
     *
     * @return ExitCode Always ExitCode::Usage.
     */
    private function printUnknownOption(string $option, $stderr): ExitCode
    {
        fwrite($stderr, Honeybee::NAME . ": unknown option '{$option}'. Try '--help'.\n");

        return ExitCode::Usage;
    }
}
