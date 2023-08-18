<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthManager;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Table;
use Laravel\Pulse\Pulse;

class HandleCacheInteraction
{
    /**
     * Create a new handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected AuthManager $auth,
    ) {
        //
    }

    /**
     * Handle a cache miss.
     */
    public function __invoke(CacheHit|CacheMissed $event): void
    {
        $this->pulse->rescue(function () use ($event) {
            $now = new CarbonImmutable();

            if (str_starts_with($event->key, 'illuminate:')) {
                return;
            }

            $this->pulse->record(new Entry(Table::CacheHit, [
                'date' => $now->toDateTimeString(),
                'hit' => $event instanceof CacheHit,
                'key' => $event->key,
                'user_id' => $this->auth->hasUser()
                    ? $this->auth->id()
                    : fn () => $this->auth->id(),
            ]));
        });
    }
}
