<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The suite runs against the real Redis cache store (so cached values go through production
        // serialization). Start every test from an empty cache.
        Cache::flush();
    }
}
