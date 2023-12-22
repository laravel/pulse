<?php

namespace Laravel\Pulse\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

interface ResolvesUsers
{
    /**
     * Return a unique key identifying the user.
     */
    public function key(Authenticatable $user): int|string|null;

    /**
     * Eager load the users with the given keys.
     *
     * @param  Collection<int, int|string|null>  $keys
     */
    public function load(Collection $keys): self;

    /**
     * Find the user with the given key.
     *
     * @return object{name: string, extra?: string, avatar?: string}
     */
    public function find(int|string|null $key): object;
}
