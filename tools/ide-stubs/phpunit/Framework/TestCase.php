<?php

declare(strict_types=1);

namespace PHPUnit\Framework;

/**
 * Minimal stub for IDE analysis when `vendor/phpunit/phpunit` is not installed locally.
 * Real types come from Composer dependencies in CI and on developer machines.
 */
abstract class TestCase
{
    protected function setUp(): void {}

    protected function tearDown(): void {}

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void {}

    public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void {}

    /** @return never */
    public static function fail(string $message = ''): void {}
}
