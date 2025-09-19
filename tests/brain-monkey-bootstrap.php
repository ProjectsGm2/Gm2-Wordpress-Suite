<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Composer autoload file is missing. Run "composer install" before executing the Brain Monkey suite.');
}
require_once $autoload;
require_once __DIR__ . '/phpunit/BrainMonkeyTestCase.php';

// Prime and reset Brain Monkey once so subsequent tests start from a clean slate.
\Brain\Monkey\setUp();
\Brain\Monkey\tearDown();
