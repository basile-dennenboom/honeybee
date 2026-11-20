<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Tests\Integration;

use RuntimeException;

/**
 * Gives a test case a fresh, empty directory that is removed again after each test.
 */
trait UsesTemporaryDirectory
{
    private string $directory = '';

    /**
     * Create an empty temporary directory before each test.
     *
     * @return void
     *
     * @throws RuntimeException When the directory cannot be created.
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/honeybee-test-' . bin2hex(random_bytes(6));

        if (!mkdir($this->directory)) {
            throw new RuntimeException("Could not create {$this->directory}.");
        }
    }

    /**
     * Remove the temporary directory and the files in it after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($this->directory);
    }

    /**
     * Write a .env file with the given contents into the temporary directory.
     *
     * @param string $contents File contents.
     *
     * @return void
     *
     * @throws RuntimeException When the file cannot be written.
     */
    private function writeEnvFile(string $contents): void
    {
        if (file_put_contents($this->directory . '/.env', $contents) === false) {
            throw new RuntimeException("Could not write {$this->directory}/.env.");
        }
    }
}
