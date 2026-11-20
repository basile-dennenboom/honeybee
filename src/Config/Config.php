<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Config;

use Basileaelterman\Honeybee\Enums\Framework;

/**
 * Honeybee's validated settings, read from its own .env file by ConfigLoader.
 */
final readonly class Config
{
    public function __construct(
        public string $sshHost,
        public ?string $sshUser,
        public ?int $sshPort,
        public ?string $sshIdentityFile,
        public string $appPath,
        public string $remotePhp,
        public Framework $framework,
        public ?string $healthUrl,
        public int $healthTimeout,
        public int $refreshInterval,
    ) {}
}
