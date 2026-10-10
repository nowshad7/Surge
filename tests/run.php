<?php

declare(strict_types=1);

/*
 * Zero-dependency test runner. Discovers every *Test.php under tests/, runs
 * each public method starting with "test", and reports results. Exits non-zero
 * on any failure so it can gate CI.
 */

use Nsd7\Surge\Tests\AssertionFailed;
use Nsd7\Surge\Tests\TestCase;

require __DIR__ . '/../autoload.php';
require __DIR__ . '/TestCase.php';

$root = __DIR__;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

$testFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
        $testFiles[] = $file->getPathname();
    }
}
sort($testFiles);

foreach ($testFiles as $file) {
    require_once $file;
}

$passed = 0;
$failed = 0;
$assertions = 0;
$failures = [];

foreach (get_declared_classes() as $class) {
    if (!is_subclass_of($class, TestCase::class)) {
        continue;
    }

    $reflection = new ReflectionClass($class);
    if ($reflection->isAbstract()) {
        continue;
    }

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        /** @var TestCase $instance */
        $instance = $reflection->newInstance();
        $name = $class . '::' . $method->getName();

        try {
            $method->invoke($instance);
            $assertions += $instance->assertions;
            $passed++;
            fwrite(STDOUT, "\033[32m.\033[0m");
        } catch (AssertionFailed $e) {
            $assertions += $instance->assertions;
            $failed++;
            $failures[] = [$name, 'assertion', $e->getMessage()];
            fwrite(STDOUT, "\033[31mF\033[0m");
        } catch (\Throwable $e) {
            $failed++;
            $failures[] = [$name, 'error', $e::class . ': ' . $e->getMessage()];
            fwrite(STDOUT, "\033[31mE\033[0m");
        }
    }
}

fwrite(STDOUT, "\n\n");

foreach ($failures as [$name, $type, $message]) {
    fwrite(STDOUT, "\033[31m[{$type}]\033[0m {$name}\n    {$message}\n\n");
}

$total = $passed + $failed;
$summary = sprintf(
    "Tests: %d, Passed: %d, Failed: %d, Assertions: %d\n",
    $total,
    $passed,
    $failed,
    $assertions,
);

if ($failed > 0) {
    fwrite(STDOUT, "\033[31m{$summary}\033[0m");
    exit(1);
}

fwrite(STDOUT, "\033[32mOK — {$summary}\033[0m");
exit(0);
