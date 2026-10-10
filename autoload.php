<?php

declare(strict_types=1);

/*
 * Minimal PSR-4 autoloader so Surge runs with just `php`, no `composer install`
 * required. When installed as a dependency, Composer's own autoloader is used
 * instead and this file is simply ignored.
 */

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Nsd7\\Surge\\Tests\\' => __DIR__ . '/tests/',
        'Nsd7\\Surge\\'        => __DIR__ . '/src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
