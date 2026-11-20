<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->append([__DIR__ . '/bin/honeybee', __FILE__]);

return new Config()
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules([
        // PER Coding Style 2.0, pinned so a PHP-CS-Fixer update never changes the style.
        '@PER-CS2x0' => true,
        'declare_strict_types' => true,
        // Every function keeps its full docblock (see docs/internal/conventions.md).
        'no_superfluous_phpdoc_tags' => false,
        'phpdoc_to_comment' => false,
    ]);
