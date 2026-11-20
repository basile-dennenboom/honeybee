<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Enums;

/**
 * Frameworks Honeybee can monitor, keyed by their HONEYBEE_FRAMEWORK value.
 */
enum Framework: string
{
    case Laravel = 'laravel';
}
