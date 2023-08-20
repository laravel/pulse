<?php

namespace Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Pulse\PulseServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use LazilyRefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            PulseServiceProvider::class,
        ];
    }
}
