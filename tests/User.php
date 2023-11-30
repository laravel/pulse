<?php

namespace Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthUser;

class User extends AuthUser
{
    use HasFactory;

    protected static function newFactory()
    {
        return new class extends Factory
        {
            protected $model = User::class;

            public function definition()
            {
                return [
                    'name' => $this->faker->name(),
                ];
            }
        };
    }
}
