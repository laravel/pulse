<?php

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Usage;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    Pulse::authorizeUsing(fn () => true);

    $this
        ->get('/pulse')
        ->assertSeeLivewire(Usage::class);
});

it('renders top 10 users making requests', function () {
    $users = User::factory(3)->create();
    Pulse::resolveUsersUsing(fn () => $users);
    Pulse::ignore(fn () => DB::table('pulse_requests')->insert([
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[0]->id, 'route' => 'GET /', 'duration' => 123, 'slow' => false],
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[0]->id, 'route' => 'GET /', 'duration' => 123, 'slow' => false],
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[0]->id, 'route' => 'GET /', 'duration' => 123, 'slow' => false],
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[1]->id, 'route' => 'GET /', 'duration' => 123, 'slow' => false],
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[1]->id, 'route' => 'GET /', 'duration' => 123, 'slow' => false],
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[2]->id, 'route' => 'GET /', 'duration' => 123, 'slow' => false],
    ]));

    Carbon::setTestNow('2000-01-02 03:04:15');

    Livewire::test(Usage::class, ['lazy' => false])
        ->assertViewHas('time')
        ->assertViewHas('runAt', '2000-01-02 03:04:15')
        ->assertViewHas('userRequestCounts', collect([
            ['count' => 3, 'user' => ['name' => $users[0]->name, 'extra' => '', 'avatar' => null]],
            ['count' => 2, 'user' => ['name' => $users[1]->name, 'extra' => '', 'avatar' => null]],
            ['count' => 1, 'user' => ['name' => $users[2]->name, 'extra' => '', 'avatar' => null]],
        ]));
});

it('renders top 10 users experiencing slow endpoints', function () {
    $users = User::factory(2)->create();
    Pulse::resolveUsersUsing(fn () => $users);
    Pulse::ignore(fn () => DB::table('pulse_requests')->insert([
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[0]->id, 'route' => 'GET /', 'duration' => 1234, 'slow' => true],
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[0]->id, 'route' => 'GET /', 'duration' => 1234, 'slow' => true],
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[1]->id, 'route' => 'GET /', 'duration' => 1234, 'slow' => true],
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[1]->id, 'route' => 'GET /', 'duration' => 123, 'slow' => true],
    ]));

    Carbon::setTestNow('2000-01-02 03:04:15');

    Livewire::withQueryParams(['usage' => 'slow_endpoint_counts'])
        ->test(Usage::class, ['lazy' => false])
        ->assertViewHas('time')
        ->assertViewHas('runAt', '2000-01-02 03:04:15')
        ->assertViewHas('userRequestCounts', collect([
            ['count' => 2, 'user' => ['name' => $users[0]->name, 'extra' => '', 'avatar' => null]],
            ['count' => 1, 'user' => ['name' => $users[1]->name, 'extra' => '', 'avatar' => null]],
        ]));
});

it('renders top 10 users dispatching jobs', function () {
    $users = User::factory(2)->create();
    Pulse::resolveUsersUsing(fn () => $users);
    Pulse::ignore(fn () => DB::table('pulse_jobs')->insert([
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[0]->id, 'job' => 'Foo', 'job_uuid' => Str::uuid(), 'attempt' => 1, 'connection' => 'database', 'queue' => 'default', 'queued_at' => '2000-01-02 03:04:05'],
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[0]->id, 'job' => 'Foo', 'job_uuid' => Str::uuid(), 'attempt' => 1, 'connection' => 'database', 'queue' => 'default', 'queued_at' => '2000-01-02 03:04:05'],
        ['date' => '2000-01-02 03:04:05', 'user_id' => $users[1]->id, 'job' => 'Foo', 'job_uuid' => Str::uuid(), 'attempt' => 1, 'connection' => 'database', 'queue' => 'default', 'queued_at' => '2000-01-02 03:04:05'],
    ]));

    Carbon::setTestNow('2000-01-02 03:04:15');

    Livewire::withQueryParams(['usage' => 'dispatched_job_counts'])
        ->test(Usage::class, ['lazy' => false])
        ->assertViewHas('time')
        ->assertViewHas('runAt', '2000-01-02 03:04:15')
        ->assertViewHas('userRequestCounts', collect([
            ['count' => 2, 'user' => ['name' => $users[0]->name, 'extra' => '', 'avatar' => null]],
            ['count' => 1, 'user' => ['name' => $users[1]->name, 'extra' => '', 'avatar' => null]],
        ]));
});

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
