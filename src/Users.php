<?php

namespace Laravel\Pulse;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\ResolvesUsers;

class Users implements ResolvesUsers
{
    /**
     * The resolved users.
     *
     * @var Collection<int, \Illuminate\Contracts\Auth\Authenticatable>
     */
    protected Collection $resolvedUsers;

    /**
     * The field resolver.
     *
     * @var ?callable(\Illuminate\Contracts\Auth\Authenticatable): array{name: string, extra?: string, avatar?: string}
     */
    protected $fieldResolver = null;

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
        $auth = app('auth');

        $provider = $auth->createUserProvider(
            config("auth.guards.{$auth->getDefaultDriver()}.provider")
        );

        if ($provider instanceof EloquentUserProvider) {
            $model = $provider->getModel();

            $this->resolvedUsers = $model::findMany($keys);
        } else {
            $this->resolvedUsers = $keys->map(fn ($key) => $provider->retrieveById($key));
        }

        return $this;
    }

    /**
     * Resolve the user fields for the user with the given key.
     *
     * @return array{name: string, extra?: string, avatar?: string}
     */
    public function fields(int|string|null $key): array
    {
        $user = $this->resolvedUsers->first(fn ($user) => $user->getAuthIdentifier() == $key);

        if ($this->fieldResolver !== null && $user !== null) {
            return ($this->fieldResolver)($user);
        }

        return [
            'name' => $user->name ?? "ID: $key",
            'extra' => $user->email ?? '',
            'avatar' => $user->avatar ?? (($user->email ?? false)
                ? sprintf('https://gravatar.com/avatar/%s?d=mp', hash('sha256', trim(strtolower($user->email)))) // @phpstan-ignore property.nonObject
                : sprintf('https://gravatar.com/avatar?d=mp')
            ),
        ];
    }

    /**
     * Override the field resolver.
     *
     * @param  callable(\Illuminate\Contracts\Auth\Authenticatable): array{name: string, extra?: string, avatar?: string}  $resolver
     */
    public function setFieldResolver(callable $resolver): self
    {
        $this->fieldResolver = $resolver;

        return $this;
    }
}
