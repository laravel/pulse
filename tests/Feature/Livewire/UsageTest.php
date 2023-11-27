<?php

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
    Pulse::resolveUsersUsing(fn () => $users);
    Carbon::setTestNow(now()->setSeconds(30));
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_entries')->insert([
        ['timestamp' => $timestamp - 3600 + 1, 'type' => $type, 'key' => $users[0]->id, 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => $type, 'key' => $users[0]->id, 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => $type, 'key' => $users[0]->id, 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => $type, 'key' => $users[1]->id, 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => $type, 'key' => $users[1]->id, 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => $type, 'key' => $users[2]->id, 'value' => 1],
    ]));
    $currentBucket = (int) floor($timestamp / 60) * 60;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => $currentBucket, 'period' => 60, 'type' => $type, 'aggregate' => 'sum', 'key' => $users[0]->id, 'value' => 3, 'count' => 3],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => $type, 'aggregate' => 'sum', 'key' => $users[1]->id, 'value' => 2, 'count' => 2],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => $type, 'aggregate' => 'sum', 'key' => $users[2]->id, 'value' => 1, 'count' => 1],
    ]));

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
