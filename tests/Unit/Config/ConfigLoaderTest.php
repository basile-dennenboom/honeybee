<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Tests\Unit\Config;

use Basileaelterman\Honeybee\Config\Config;
use Basileaelterman\Honeybee\Config\ConfigException;
use Basileaelterman\Honeybee\Config\ConfigLoader;
use Basileaelterman\Honeybee\Enums\Framework;
use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigLoader::class)]
#[CoversClass(ConfigException::class)]
#[UsesClass(Config::class)]
#[UsesClass(Framework::class)]
final class ConfigLoaderTest extends TestCase
{
    private const array REQUIRED = [
        'HONEYBEE_SSH_HOST' => 'myapp-production',
        'HONEYBEE_APP_PATH' => '/var/www/myapp/current',
    ];

    /**
     * Provide invalid values for the integer settings, with the expected error fragment.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function invalidNumberProvider(): array
    {
        return [
            'interval not a number' => ['HONEYBEE_REFRESH_INTERVAL', 'abc', 'positive whole number'],
            'interval zero' => ['HONEYBEE_REFRESH_INTERVAL', '0', 'positive whole number'],
            'interval negative' => ['HONEYBEE_REFRESH_INTERVAL', '-5', 'positive whole number'],
            'timeout decimal' => ['HONEYBEE_HEALTH_TIMEOUT', '1.5', 'positive whole number'],
            'timeout overflow' => ['HONEYBEE_HEALTH_TIMEOUT', '99999999999999999999', 'positive whole number'],
            'port too large' => ['HONEYBEE_SSH_PORT', '70000', 'from 1 to 65535'],
            'port zero' => ['HONEYBEE_SSH_PORT', '0', 'from 1 to 65535'],
        ];
    }

    /**
     * Provide health URLs that are not valid http(s) URLs.
     *
     * @return array<string, array{string}>
     */
    public static function invalidHealthUrlProvider(): array
    {
        return [
            'ftp scheme' => ['ftp://example.com/up'],
            'not a url' => ['not a url'],
            'no scheme' => ['example.com/up'],
        ];
    }

    /**
     * With only the required keys set, every other setting gets its default.
     *
     * @return void
     */
    public function testDefaultsAreApplied(): void
    {
        $config = new ConfigLoader()->fromArray(self::REQUIRED);

        self::assertSame('myapp-production', $config->sshHost);
        self::assertSame('/var/www/myapp/current', $config->appPath);
        self::assertNull($config->sshUser);
        self::assertNull($config->sshPort);
        self::assertNull($config->sshIdentityFile);
        self::assertSame('php', $config->remotePhp);
        self::assertSame(Framework::Laravel, $config->framework);
        self::assertNull($config->healthUrl);
        self::assertSame(5, $config->healthTimeout);
        self::assertSame(30, $config->refreshInterval);
    }

    /**
     * Every setting given explicitly ends up in the Config, with numbers as integers.
     *
     * @return void
     */
    public function testExplicitValuesAreUsed(): void
    {
        $config = new ConfigLoader()->fromArray(self::REQUIRED + [
            'HONEYBEE_SSH_USER' => 'deploy',
            'HONEYBEE_SSH_PORT' => '2222',
            'HONEYBEE_SSH_IDENTITY_FILE' => '~/.ssh/id_ed25519',
            'HONEYBEE_REMOTE_PHP' => 'php8.5',
            'HONEYBEE_FRAMEWORK' => 'Laravel',
            'HONEYBEE_HEALTH_URL' => 'https://myapp.example/up',
            'HONEYBEE_HEALTH_TIMEOUT' => '3',
            'HONEYBEE_REFRESH_INTERVAL' => '60',
        ]);

        self::assertSame('deploy', $config->sshUser);
        self::assertSame(2222, $config->sshPort);
        self::assertSame('~/.ssh/id_ed25519', $config->sshIdentityFile);
        self::assertSame('php8.5', $config->remotePhp);
        self::assertSame(Framework::Laravel, $config->framework);
        self::assertSame('https://myapp.example/up', $config->healthUrl);
        self::assertSame(3, $config->healthTimeout);
        self::assertSame(60, $config->refreshInterval);
    }

    /**
     * Empty and null values count as unset, so defaults apply.
     *
     * @return void
     */
    public function testEmptyValuesCountAsUnset(): void
    {
        $config = new ConfigLoader()->fromArray(self::REQUIRED + [
            'HONEYBEE_SSH_USER' => '',
            'HONEYBEE_SSH_PORT' => '   ',
            'HONEYBEE_REMOTE_PHP' => null,
            'HONEYBEE_HEALTH_URL' => '',
            'HONEYBEE_REFRESH_INTERVAL' => '',
        ]);

        self::assertNull($config->sshUser);
        self::assertNull($config->sshPort);
        self::assertSame('php', $config->remotePhp);
        self::assertNull($config->healthUrl);
        self::assertSame(30, $config->refreshInterval);
    }

    /**
     * Both missing required keys are reported together in one exception.
     *
     * @return void
     */
    public function testMissingRequiredKeysAreAllReported(): void
    {
        $exception = $this->expectConfigException(['HONEYBEE_SSH_HOST' => '']);

        self::assertCount(2, $exception->problems);
        self::assertStringContainsString('HONEYBEE_SSH_HOST is required', $exception->problems[0]);
        self::assertStringContainsString('HONEYBEE_APP_PATH is required', $exception->problems[1]);
        self::assertSame(implode("\n", $exception->problems), $exception->getMessage());
    }

    /**
     * Integer settings reject anything that is not a whole number in range.
     *
     * @param string $name Variable to set.
     * @param string $value Invalid value.
     * @param string $expected Fragment the error message must contain.
     *
     * @return void
     */
    #[DataProvider('invalidNumberProvider')]
    public function testInvalidNumbersAreRejected(string $name, string $value, string $expected): void
    {
        $exception = $this->expectConfigException(self::REQUIRED + [$name => $value]);

        self::assertCount(1, $exception->problems);
        self::assertStringContainsString($name, $exception->problems[0]);
        self::assertStringContainsString($expected, $exception->problems[0]);
        self::assertStringContainsString("\"{$value}\"", $exception->problems[0]);
    }

    /**
     * An unknown framework is rejected and the supported ones are listed.
     *
     * @return void
     */
    public function testUnknownFrameworkIsRejected(): void
    {
        $exception = $this->expectConfigException(self::REQUIRED + ['HONEYBEE_FRAMEWORK' => 'symfony']);

        self::assertSame(['HONEYBEE_FRAMEWORK must be one of: laravel; got "symfony".'], $exception->problems);
    }

    /**
     * A relative application path is rejected.
     *
     * @return void
     */
    public function testRelativeAppPathIsRejected(): void
    {
        $exception = $this->expectConfigException([
            'HONEYBEE_SSH_HOST' => 'myapp-production',
            'HONEYBEE_APP_PATH' => 'var/www/myapp',
        ]);

        self::assertCount(1, $exception->problems);
        self::assertStringContainsString('HONEYBEE_APP_PATH must be an absolute path', $exception->problems[0]);
    }

    /**
     * A health URL that is not a valid http(s) URL is rejected.
     *
     * @param string $url Invalid URL.
     *
     * @return void
     */
    #[DataProvider('invalidHealthUrlProvider')]
    public function testInvalidHealthUrlIsRejected(string $url): void
    {
        $exception = $this->expectConfigException(self::REQUIRED + ['HONEYBEE_HEALTH_URL' => $url]);

        self::assertSame(["HONEYBEE_HEALTH_URL must be an http:// or https:// URL, got \"{$url}\"."], $exception->problems);
    }

    /**
     * The inline-comment format of .env.example, including empty values followed by a comment, parses correctly.
     *
     * @return void
     */
    public function testEnvExampleInlineCommentFormat(): void
    {
        $contents = <<<'ENV'
            # --- Connection ----------------------------------------------------------

            HONEYBEE_SSH_HOST=myapp-production   # Hostname, IP or ~/.ssh/config alias (required).

            HONEYBEE_SSH_USER=
            HONEYBEE_SSH_PORT=2222
            HONEYBEE_SSH_IDENTITY_FILE=

            HONEYBEE_APP_PATH=/var/www/myapp/current   # Absolute path (required).
            HONEYBEE_REMOTE_PHP=php         # PHP binary on the server.

            HONEYBEE_FRAMEWORK=laravel      # Only "laravel" is supported for now.

            HONEYBEE_HEALTH_URL=            # Leave empty to use the app's APP_URL + /up.
            HONEYBEE_HEALTH_TIMEOUT=5       # Seconds before a health check counts as failed.

            # --- Display -------------------------------------------------------------

            HONEYBEE_REFRESH_INTERVAL=30    # Seconds between storage scans and health checks.
            ENV;

        $config = new ConfigLoader()->fromArray(Dotenv::parse($contents));

        self::assertEquals(
            new Config(
                sshHost: 'myapp-production',
                sshUser: null,
                sshPort: 2222,
                sshIdentityFile: null,
                appPath: '/var/www/myapp/current',
                remotePhp: 'php',
                framework: Framework::Laravel,
                healthUrl: null,
                healthTimeout: 5,
                refreshInterval: 30,
            ),
            $config,
        );
    }

    /**
     * Run fromArray() with values that must fail and return the exception.
     *
     * @param array<string, string|null> $values Raw values to validate.
     *
     * @return ConfigException The exception thrown.
     */
    private function expectConfigException(array $values): ConfigException
    {
        try {
            new ConfigLoader()->fromArray($values);
        } catch (ConfigException $exception) {
            return $exception;
        }

        self::fail('Expected a ConfigException.');
    }
}
