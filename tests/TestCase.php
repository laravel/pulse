<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Laravel\Pulse\PulseServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function defineEnvironment($app)
    {
        // Artisan::call('vendor:publish', ['--tag' => 'pulse-assets']);
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            PulseServiceProvider::class,
        ];
    }
}
