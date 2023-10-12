<?php

namespace Laravel\Pulse\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Pulse\Pulse;

class PulseRecording
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(protected Pulse $pulse)
    {
        //
    }

    /**
     * Start recording.
     */
    public static function start(): string
    {
        return static::class.':start,'.Str::random();
    }

    /**
     * Stop recording.
     */
    public static function stop(): string
    {
        return static::class.':stop,'.Str::random();
    }

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next, string $action): mixed
    {
        match ($action) {
            'start' => $this->pulse->startRecording(),
            'stop' => $this->pulse->stopRecording(),
        };

        return $next($request);
    }
}
