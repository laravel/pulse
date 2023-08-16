<?php

namespace Laravel\Pulse\Packages\Deployments;

class ServiceProvider
{
    public function boot()
    {
        Event::listen(Deployed::class, function ($event) {
            Pulse::record(new Entry('pulse_deployments', [
                'date' => now()->toDateTimeString(),
                'duration' => $event->time,
            ]));
        });

        Livewire::component('deployments', Deployments::class);

        App::when(Deployments::class)->needs('query')->give(new Deployment);
    }
}

// My application

class Provider
{
    public function boot()
    {
        App::when(Deployments::class)->needs('query')->give(function ($interval) {
            return DB::whatever();
        });
    }
}
