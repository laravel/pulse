<?php

use Illuminate\Http\JsonResponse as IlluminateJsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\ValidationErrors;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;

use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(fn () => Pulse::register([ValidationErrors::class => []]));

it('captures validation errors from the session', function () {
    Route::post('users', fn () => Request::validate([
        'email' => 'required',
    ]))->middleware('web');

    $response = post('users');

    $response->assertStatus(302);
    $response->assertInvalid('email');
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('["POST","\/users","Closure","email"]');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates->pluck('key')->all())->toBe(array_fill(0, 4, '["POST","\/users","Closure","email"]'));
    expect($aggregates->pluck('aggregate')->all())->toBe(array_fill(0, 4, 'count'));
    expect($aggregates->pluck('value')->all())->toBe(array_fill(0, 4, '1.00'));
});

it('does not capture validation errors from redirects when there is no session', function () {
    Route::post('users', fn () => Request::validate([
        'email' => 'required',
    ]));

    $response = post('users');

    $response->assertStatus(302);
    $response->assertInvalid('email');
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(0);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(0);
});

it('does not capture validation errors from redirects when the "errors" key is not a ViewErrorBag with session', function () {
    Route::post('users', fn () => redirect()->back()->with('errors', 'Something happened!'))->middleware('web');

    $response = post('users');

    $response->assertStatus(302);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(0);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(0);
});


it('captures one entry for a field when multiple errors are present for the given field from the session', function () {
    Route::post('users', fn () => Request::validate([
        'email' => 'string|min:5',
    ]))->middleware('web');

    $response = post('users', [
        'email' => 4,
    ]);

    $response->assertStatus(302);
    $response->assertInvalid([
        'email' => [
            'The email field must be a string.',
            'The email field must be at least 5 characters.',
        ],
    ]);
    $response->assertInvalid(['email' => 'The email field must be at least 5 characters.']);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('["POST","\/users","Closure","email"]');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates->pluck('key')->all())->toBe(array_fill(0, 4, '["POST","\/users","Closure","email"]'));
    expect($aggregates->pluck('aggregate')->all())->toBe(array_fill(0, 4, 'count'));
    expect($aggregates->pluck('value')->all())->toBe(array_fill(0, 4, '1.00'));
});

it('captures a generic error when it is unable to parse the validation error fields from the session', function () {
    Route::post('users', fn () => response('<p>An error occurred.</p>', 422))->middleware('web');

    $response = post('users');

    $response->assertStatus(422);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('["POST","\/users","Closure","__unknown"]');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates->pluck('key')->all())->toBe(array_fill(0, 4, '["POST","\/users","Closure","__unknown"]'));
    expect($aggregates->pluck('aggregate')->all())->toBe(array_fill(0, 4, 'count'));
    expect($aggregates->pluck('value')->all())->toBe(array_fill(0, 4, '1.00'));
});

it('captures API validation errors', function () {
    Route::post('users', fn () => Request::validate([
        'email' => 'required',
    ]))->middleware('api');

    $response = postJson('users');

    $response->assertStatus(422);
    $response->assertInvalid('email');
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('["POST","\/users","Closure","email"]');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates->pluck('key')->all())->toBe(array_fill(0, 4, '["POST","\/users","Closure","email"]'));
    expect($aggregates->pluck('aggregate')->all())->toBe(array_fill(0, 4, 'count'));
    expect($aggregates->pluck('value')->all())->toBe(array_fill(0, 4, '1.00'));
});

it('captures "unknown" API validation error for non Illuminate Json responses', function () {
    Route::post('users', fn () => new SymfonyJsonResponse(['errors' => ['email' => 'Is required.']], 422))
        ->middleware('api');

    $response = postJson('users');

    $response->assertStatus(422);
    $response->assertInvalid('email');
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('["POST","\/users","Closure","__unknown"]');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates->pluck('key')->all())->toBe(array_fill(0, 4, '["POST","\/users","Closure","__unknown"]'));
    expect($aggregates->pluck('aggregate')->all())->toBe(array_fill(0, 4, 'count'));
    expect($aggregates->pluck('value')->all())->toBe(array_fill(0, 4, '1.00'));
});

it('captures "unknown" API validation error for non array Json content', function () {
    Route::post('users', fn () => new IlluminateJsonResponse('An error occurred.', 422))
        ->middleware('api');

    $response = postJson('users');

    $response->assertStatus(422);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('["POST","\/users","Closure","__unknown"]');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates->pluck('key')->all())->toBe(array_fill(0, 4, '["POST","\/users","Closure","__unknown"]'));
    expect($aggregates->pluck('aggregate')->all())->toBe(array_fill(0, 4, 'count'));
    expect($aggregates->pluck('value')->all())->toBe(array_fill(0, 4, '1.00'));
});

it('captures "unknown" API validation error for array content mising "errors" key', function () {
    Route::post('users', fn () => new IlluminateJsonResponse(['An error occurred.'], 422))
        ->middleware('api');

    $response = postJson('users');

    $response->assertStatus(422);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('["POST","\/users","Closure","__unknown"]');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates->pluck('key')->all())->toBe(array_fill(0, 4, '["POST","\/users","Closure","__unknown"]'));
    expect($aggregates->pluck('aggregate')->all())->toBe(array_fill(0, 4, 'count'));
    expect($aggregates->pluck('value')->all())->toBe(array_fill(0, 4, '1.00'));
});

it('captures "unknown" API validation error for "errors" key that does not contain an array', function () {
    Route::post('users', fn () => new IlluminateJsonResponse(['errors' => 'An error occurred.'], 422))
        ->middleware('api');

    $response = postJson('users');

    $response->assertStatus(422);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('["POST","\/users","Closure","__unknown"]');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates->pluck('key')->all())->toBe(array_fill(0, 4, '["POST","\/users","Closure","__unknown"]'));
    expect($aggregates->pluck('aggregate')->all())->toBe(array_fill(0, 4, 'count'));
    expect($aggregates->pluck('value')->all())->toBe(array_fill(0, 4, '1.00'));
});

it('captures "unknown" API validation error for "errors" key that contains a list', function () {
    Route::post('users', fn () => new IlluminateJsonResponse(['errors' => ['An error occurred.']], 422))
        ->middleware('api');

    $response = postJson('users');

    $response->assertStatus(422);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('["POST","\/users","Closure","__unknown"]');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates->pluck('key')->all())->toBe(array_fill(0, 4, '["POST","\/users","Closure","__unknown"]'));
    expect($aggregates->pluck('aggregate')->all())->toBe(array_fill(0, 4, 'count'));
    expect($aggregates->pluck('value')->all())->toBe(array_fill(0, 4, '1.00'));
});
