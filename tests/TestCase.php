<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // The API is only ever called by the SPA. Sanctum starts a session
        // (needed by the cookie-based auth flow) only for requests it
        // recognises as coming from a stateful frontend, so present every
        // test request as originating from a configured stateful domain.
        $this->withHeader('Origin', 'http://'.config('sanctum.stateful')[0]);
    }
}
