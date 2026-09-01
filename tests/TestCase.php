<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base Test Case
 *
 * Provides shared test environment initialization, disabling Vite bundling, CSRF checks, and setting test configurations.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.disable_test_admin_bypass' => false]);
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }
}
