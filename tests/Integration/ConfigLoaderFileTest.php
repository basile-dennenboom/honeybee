<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Tests\Integration;

use Basileaelterman\Honeybee\Config\Config;
use Basileaelterman\Honeybee\Config\ConfigException;
use Basileaelterman\Honeybee\Config\ConfigLoader;
use Basileaelterman\Honeybee\Enums\Framework;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Checks that ConfigLoader::load() reads a real .env file from disk.
 */
#[CoversClass(ConfigLoader::class)]
#[CoversClass(ConfigException::class)]
#[UsesClass(Config::class)]
#[UsesClass(Framework::class)]
final class ConfigLoaderFileTest extends TestCase
{
    use UsesTemporaryDirectory;

    private const string ENV_EXAMPLE = __DIR__ . '/../../.env.example';

    /**
     * A valid .env file is loaded into a Config.
     *
     * @return void
     *
     * @throws RuntimeException When the .env file cannot be written.
     */
    public function testLoadsValidEnvFile(): void
    {
        $this->writeEnvFile("HONEYBEE_SSH_HOST=myapp-production\nHONEYBEE_APP_PATH=/var/www/myapp\nHONEYBEE_REFRESH_INTERVAL=10\n");

        $config = new ConfigLoader()->load($this->directory);

        self::assertSame('myapp-production', $config->sshHost);
        self::assertSame('/var/www/myapp', $config->appPath);
        self::assertSame(10, $config->refreshInterval);
    }

    /**
     * A directory without a .env file gives a clear error.
     *
     * @return void
     */
    public function testMissingEnvFileIsReported(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("No .env file found in {$this->directory}");

        new ConfigLoader()->load($this->directory);
    }

    /**
     * A .env file with a syntax error gives a clear error.
     *
     * @return void
     *
     * @throws RuntimeException When the .env file cannot be written.
     */
    public function testUnparseableEnvFileIsReported(): void
    {
        $this->writeEnvFile("HONEYBEE_SSH_HOST=\"unterminated\n");

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('could not be parsed');

        new ConfigLoader()->load($this->directory);
    }

    /**
     * The shipped .env.example, used unchanged, parses and only lacks the two required values.
     *
     * @return void
     *
     * @throws RuntimeException When .env.example cannot be read or the .env file cannot be written.
     */
    public function testUnchangedEnvExampleOnlyLacksRequiredValues(): void
    {
        $example = file_get_contents(self::ENV_EXAMPLE);

        if ($example === false) {
            throw new RuntimeException('Could not read .env.example.');
        }

        $this->writeEnvFile($example);

        try {
            new ConfigLoader()->load($this->directory);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            self::assertCount(2, $exception->problems);
            self::assertStringStartsWith('HONEYBEE_SSH_HOST is required', $exception->problems[0]);
            self::assertStringStartsWith('HONEYBEE_APP_PATH is required', $exception->problems[1]);
        }
    }
}
