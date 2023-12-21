<?php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Usage;
use Livewire\Livewire;
use Orchestra\Testbench\Factories\UserFactory;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(Usage::class);
});

it('renders top 10 users making requests', function (string $query, string $type) {
    $users = User::factory(3)->create();

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

    Pulse::ingest();

    Livewire::withQueryParams(['usage' => $query])
        ->test(Usage::class, ['lazy' => false])
        ->assertViewHas('userRequestCounts', collect([
            (object) ['key' => $users[0]->id, 'count' => 6, 'user' => (object) ['name' => $users[0]->name, 'extra' => $users[0]->email, 'avatar' => avatar($users[0]->email)]],
            (object) ['key' => $users[1]->id, 'count' => 4, 'user' => (object) ['name' => $users[1]->name, 'extra' => $users[1]->email, 'avatar' => avatar($users[1]->email)]],
            (object) ['key' => $users[2]->id, 'count' => 2, 'user' => (object) ['name' => $users[2]->name, 'extra' => $users[2]->email, 'avatar' => avatar($users[2]->email)]],
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
        return new class extends UserFactory
        {
            protected $model = User::class;
        };
    }
}
