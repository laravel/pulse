<?php

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Usage;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(Usage::class);
});

it('renders top 10 users making requests', function (string $query, string $type) {
    $users = User::factory(3)->create();
    Pulse::users(fn () => $users);

    // Add entries outside of the window.
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record($type, $users[0]->id)->count();
    Pulse::record($type, $users[1]->id)->count();
    Pulse::record($type, $users[2]->id)->count();

    // Add entries to the "tail".
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record($type, $users[0]->id)->count();
    Pulse::record($type, $users[0]->id)->count();
    Pulse::record($type, $users[0]->id)->count();
    Pulse::record($type, $users[1]->id)->count();
    Pulse::record($type, $users[1]->id)->count();
    Pulse::record($type, $users[2]->id)->count();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record($type, $users[0]->id)->count();
    Pulse::record($type, $users[0]->id)->count();
    Pulse::record($type, $users[0]->id)->count();
    Pulse::record($type, $users[1]->id)->count();
    Pulse::record($type, $users[1]->id)->count();
    Pulse::record($type, $users[2]->id)->count();

    Pulse::store();

    Livewire::withQueryParams(['usage' => $query])
        ->test(Usage::class, ['lazy' => false])
        ->assertViewHas('userRequestCounts', collect([
            (object) ['count' => 6, 'user' => (object) ['id' => $users[0]->id, 'name' => $users[0]->name, 'extra' => '', 'avatar' => null]],
            (object) ['count' => 4, 'user' => (object) ['id' => $users[1]->id, 'name' => $users[1]->name, 'extra' => '', 'avatar' => null]],
            (object) ['count' => 2, 'user' => (object) ['id' => $users[2]->id, 'name' => $users[2]->name, 'extra' => '', 'avatar' => null]],
        ]));
})->with([
    ['requests', 'user_request'],
    ['slow_requests', 'slow_user_request'],
    ['jobs', 'user_job'],
]);

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
