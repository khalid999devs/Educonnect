<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'pgsql' || ! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new RuntimeException('Tests must use a dedicated PostgreSQL database ending in _test.');
        }

        // Suppress the operational telemetry side effect by default so a test
        // that renders a 5xx or runs a job outside a rolled-back transaction
        // cannot commit rows that pollute later tests. Telemetry-focused tests
        // opt back in explicitly.
        config()->set('telemetry.enabled', false);
        config()->set('telemetry.http.enabled', false);

        // Content-derived caches are disabled by default: tests seed published
        // content through factories (which do not bump the content version), so
        // a cached bundle could otherwise serve stale content within a test.
        // Cache-focused tests opt back in explicitly.
        config()->set('performance.guidance_cache.enabled', false);
    }
}
