<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Any HTTP call that a test did not fake fails loudly, so tests can
        // never hit TMDB (or anything else) for real.
        Http::preventStrayRequests();
    }
}
