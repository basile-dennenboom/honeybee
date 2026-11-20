<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Config;

use RuntimeException;
use Throwable;

/**
 * Thrown when Honeybee's .env file is missing, unreadable or contains invalid values.
 */
final class ConfigException extends RuntimeException
{
    public function __construct(
        public readonly array $problems,
        ?Throwable $previous = null,
    ) {
        parent::__construct(implode("\n", $problems), 0, $previous);
    }
}
