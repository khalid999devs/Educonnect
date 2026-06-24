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
    }
}
