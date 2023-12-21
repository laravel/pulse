<?php

namespace Laravel\Pulse;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\ResolvesUsers;

class LegacyUsers implements ResolvesUsers
{
    /**
     * The resolved users.
     *
     * @var Collection<int, array{name: string, email?: string, extra?: string, avatar?: string}>
     */
    protected Collection $resolvedUsers;

    /**
     * @param  callable  $callback
     */
    public function __construct(protected $callback)
    {
        //
    }

    /**
     * Return a unique key identifying the user.
     */
    public function key(Authenticatable $user): int|string|null
    {
        return $user->getAuthIdentifier();
    }

    /**
     * Eager load the users with the given keys.
     *
     * @param  Collection<int, int|string|null>  $keys
     */
    public function load(Collection $keys): self
    {
        $this->resolvedUsers = ($this->callback)($keys);

        return $this;
    }

    /**
     * Resolve the user fields for the user with the given key.
     *
     * @return array{name: string, extra?: string, avatar?: string}
     */
    public function fields(int|string|null $key): array
    {
        $user = $this->resolvedUsers->firstWhere('id', $key);

        return [
            'name' => $user['name'] ?? "ID: $key",
            'extra' => $user['extra'] ?? $user['email'] ?? '',
            'avatar' => $user['avatar'] ?? (($user['email'] ?? false)
                ? sprintf('https://gravatar.com/avatar/%s?d=mp', hash('sha256', trim(strtolower($user['email']))))
                : sprintf('https://gravatar.com/avatar?d=mp')
            ),
        ];
    }
}
