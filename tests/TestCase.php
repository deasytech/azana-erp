<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The panel theme is built with `npm run build`; tests do not need the compiled files.
        $this->withoutVite();
    }
}
