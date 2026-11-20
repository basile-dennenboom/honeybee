<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee;

use Basileaelterman\Honeybee\Enums\ExitCode;

/**
 * Command-line entry point: interprets the arguments given to bin/honeybee.
 */
final class Cli
{
    /**
     * Run the command for the given arguments and return the process exit code.
     *
     * @param list<string> $arguments Command-line arguments, without the script name.
     * @param resource $stdout Stream for normal output.
     * @param resource $stderr Stream for errors.
     *
     * @return int Exit code for the process (see ExitCode).
     */
    public function run(array $arguments, $stdout, $stderr): int
    {
        if ($arguments === []) {
            fwrite($stderr, Honeybee::NAME . ": the dashboard is not implemented yet. Try '--help'.\n");

            return ExitCode::Failure->value;
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
