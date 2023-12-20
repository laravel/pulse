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
     * Eager load the users.
     *
     * @param  Collection<int, int|string|null>  $keys
     */
    public function load(Collection $keys): self;

    /**
     * Resolve the user fields.
     *
     * @return array{name: string, extra?: string, avatar?: string}
     */
    public function fields(int|string|null $key): array;
}
