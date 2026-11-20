<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Config;

use Basileaelterman\Honeybee\Enums\Framework;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Dotenv\Exception\InvalidPathException;

/**
 * Reads Honeybee's own .env file, validates it and applies defaults.
 *
 * Only the file is read: process environment variables are neither read nor changed.
 */
final class ConfigLoader
{
    private const string DEFAULT_REMOTE_PHP = 'php';

    private const Framework DEFAULT_FRAMEWORK = Framework::Laravel;

    private const int DEFAULT_HEALTH_TIMEOUT = 5;

    private const int DEFAULT_REFRESH_INTERVAL = 30;

    private const int MAX_PORT = 65535;

    /**
     * Read the .env file in the given directory and turn it into a validated Config.
     *
     * @param string $directory Directory that contains the .env file.
     *
     * @return Config The validated settings.
     *
     * @throws ConfigException When the file is missing, cannot be parsed or contains invalid values.
     */
    public function load(string $directory): Config
    {
        try {
            $values = Dotenv::createArrayBacked($directory)->load();
        } catch (InvalidPathException $exception) {
            throw new ConfigException(
                ["No .env file found in {$directory}. Copy .env.example to .env and fill it in."],
                $exception,
            );
        } catch (InvalidFileException $exception) {
            throw new ConfigException(
                ["The .env file in {$directory} could not be parsed: {$exception->getMessage()}"],
                $exception,
            );
        }

        return $this->fromArray($values);
    }

    /**
     * Validate raw .env values and turn them into a Config, applying defaults for unset values.
     *
     * @param array<string, string|null> $values Raw values keyed by variable name.
     *
     * @return Config The validated settings.
     *
     * @throws ConfigException When one or more values are missing or invalid.
     */
    public function fromArray(array $values): Config
    {
        $problems = [];

        $sshHost = $this->requiredString(
            $values,
            'HONEYBEE_SSH_HOST',
            'the hostname, IP or ~/.ssh/config alias of the application server',
            $problems,
        );

        $appPath = $this->requiredString(
            $values,
            'HONEYBEE_APP_PATH',
            'the absolute path of the application on the server',
            $problems,
        );

        if ($appPath !== '' && !str_starts_with($appPath, '/')) {
            $problems[] = "HONEYBEE_APP_PATH must be an absolute path starting with \"/\", got \"{$appPath}\".";
        }

        $sshPort = $this->positiveInt($values, 'HONEYBEE_SSH_PORT', self::MAX_PORT, $problems);
        $healthTimeout = $this->positiveInt($values, 'HONEYBEE_HEALTH_TIMEOUT', null, $problems);
        $refreshInterval = $this->positiveInt($values, 'HONEYBEE_REFRESH_INTERVAL', null, $problems);
        $framework = $this->framework($values, $problems);
        $healthUrl = $this->healthUrl($values, $problems);

        if ($problems !== []) {
            throw new ConfigException($problems);
        }

        return new Config(
            sshHost: $sshHost,
            sshUser: $this->optionalString($values, 'HONEYBEE_SSH_USER'),
            sshPort: $sshPort,
            sshIdentityFile: $this->optionalString($values, 'HONEYBEE_SSH_IDENTITY_FILE'),
            appPath: $appPath,
            remotePhp: $this->optionalString($values, 'HONEYBEE_REMOTE_PHP') ?? self::DEFAULT_REMOTE_PHP,
            framework: $framework,
            healthUrl: $healthUrl,
            healthTimeout: $healthTimeout ?? self::DEFAULT_HEALTH_TIMEOUT,
            refreshInterval: $refreshInterval ?? self::DEFAULT_REFRESH_INTERVAL,
        );
    }

    /**
     * Return a variable's trimmed value, or null when it is missing or empty.
     *
     * @param array<string, string|null> $values Raw values keyed by variable name.
     * @param string $name Variable name.
     *
     * @return string|null The non-empty value, or null.
     */
    private function optionalString(array $values, string $name): ?string
    {
        $value = trim($values[$name] ?? '');

        return $value === '' ? null : $value;
    }

    /**
     * Return a required variable's value, recording a problem when it is missing or empty.
     *
     * @param array<string, string|null> $values Raw values keyed by variable name.
     * @param string $name Variable name.
     * @param string $description What the variable holds, used in the error message.
     * @param list<string> $problems Problems found so far; a new one is appended when the value is missing.
     *
     * @return string The value, or an empty string when it is missing.
     */
    private function requiredString(array $values, string $name, string $description, array &$problems): string
    {
        $value = $this->optionalString($values, $name);

        if ($value === null) {
            $problems[] = "{$name} is required: {$description}.";

            return '';
        }

        return $value;
    }

    /**
     * Return a variable as a positive integer, recording a problem when it is not one.
     *
     * @param array<string, string|null> $values Raw values keyed by variable name.
     * @param string $name Variable name.
     * @param int|null $max Largest allowed value, or null for no limit.
     * @param list<string> $problems Problems found so far; a new one is appended when the value is invalid.
     *
     * @return int|null The number, or null when the variable is unset or invalid.
     */
    private function positiveInt(array $values, string $name, ?int $max, array &$problems): ?int
    {
        $value = $this->optionalString($values, $name);

        if ($value === null) {
            return null;
        }

        $number = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => $max ?? PHP_INT_MAX],
        ]);

        if ($number === false) {
            $problems[] = $max === null
                ? "{$name} must be a positive whole number, got \"{$value}\"."
                : "{$name} must be a whole number from 1 to {$max}, got \"{$value}\".";

            return null;
        }

        return $number;
    }

    /**
     * Return the configured framework, recording a problem when the name is not supported.
     *
     * @param array<string, string|null> $values Raw values keyed by variable name.
     * @param list<string> $problems Problems found so far; a new one is appended when the name is unknown.
     *
     * @return Framework The framework; the default when unset or unknown.
     */
    private function framework(array $values, array &$problems): Framework
    {
        $name = $this->optionalString($values, 'HONEYBEE_FRAMEWORK');

        if ($name === null) {
            return self::DEFAULT_FRAMEWORK;
        }

        $framework = Framework::tryFrom(strtolower($name));

        if ($framework === null) {
            $supported = implode(', ', array_map(
                static fn(Framework $case): string => $case->value,
                Framework::cases(),
            ));
            $problems[] = "HONEYBEE_FRAMEWORK must be one of: {$supported}; got \"{$name}\".";

            return self::DEFAULT_FRAMEWORK;
        }

        return $framework;
    }

    /**
     * Return the health URL, recording a problem when it is not a valid http(s) URL.
     *
     * @param array<string, string|null> $values Raw values keyed by variable name.
     * @param list<string> $problems Problems found so far; a new one is appended when the URL is invalid.
     *
     * @return string|null The URL, or null when unset or invalid.
     */
    private function healthUrl(array $values, array &$problems): ?string
    {
        $url = $this->optionalString($values, 'HONEYBEE_HEALTH_URL');

        if ($url === null) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $isHttp = is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);

        if (filter_var($url, FILTER_VALIDATE_URL) === false || !$isHttp) {
            $problems[] = "HONEYBEE_HEALTH_URL must be an http:// or https:// URL, got \"{$url}\".";

            return null;
        }

        return $url;
    }
}
