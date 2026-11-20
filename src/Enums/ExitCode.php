<?php

declare(strict_types=1);

namespace Basileaelterman\Honeybee\Enums;

/**
 * Process exit codes returned by bin/honeybee.
 */
enum ExitCode: int
{
    case Success = 0;
    case Failure = 1;
    case Usage = 2;
}
