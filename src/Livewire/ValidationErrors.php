<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\ValidationErrors as ValidationErrorsRecorder;
use Livewire\Attributes\Lazy;

/**
 * @internal
 */
#[Lazy]
class ValidationErrors extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        $bits = [
            ['POST', '/users', 'App\\Http\\Controllers\\UserController@store', 'password'],
            ['PATCH', '/episodes', 'App\\Http\\Controllers\\EpisodeController@update', 'version'],
            ['POST', '/shows', 'App\\Http\\Controllers\\ShowController@store', 'website'],
            ['POST', '/episodes', 'App\\Http\\Controllers\\EpisodeController@store', 'version'],
            ['DELETE', '/episodes', 'App\\Http\\Controllers\\EpisodeController@delete', 'title_confirmation'],
            ['POST', '/episodes', 'App\\Http\\Controllers\\EpisodeController@store', 'custom_feed_url'],
            ['PATCH', '/users', 'App\\Http\\Controllers\\UserController@update', 'password'],
        ];

        [$validationErrors, $time, $runAt] = $this->remember(
            fn () => Pulse::aggregate(
                'validation_error',
                ['count'],
                $this->periodAsInterval(),
            )->map(function ($row, $index) use (&$bits) {
                [$method, $uri, $action, $name] = array_shift($bits);

                return (object) [
                    'uri' => $uri,
                    'name' => $name,
                    'method' => $method,
                    'action' => $action,
                    'count' => $row->count + rand(0, 10 - $index),
                ];
            }),
        );

        return View::make('pulse::livewire.validation-errors', [
            'time' => $time,
            'runAt' => $runAt,
            'validationErrors' => $validationErrors,
            'config' => Config::get('pulse.recorders.'.ValidationErrorsRecorder::class),
        ]);
    }
}
