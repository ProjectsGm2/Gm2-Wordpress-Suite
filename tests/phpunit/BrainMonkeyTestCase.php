<?php

declare(strict_types=1);

namespace Tests\Phpunit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

/**
 * Base test case that boots Brain Monkey for hook-focused unit tests.
 */
abstract class BrainMonkeyTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        if (class_exists('Mockery')) {
            \Mockery::close();
        }
        Monkey\tearDown();
        parent::tearDown();
    }
}
