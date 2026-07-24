<?php

use Laravel\Pulse\Pulse;

it('can normalize URLs', function (string $given, string $expected) {
    expect(Pulse::normalizeUrl($given))->toBe($expected);
})->with([
    ['https://httpbin.org/delay/2', 'https://httpbin.org/delay/2'],
    ['https://laravel:password@httpbin.org/delay/2', 'https://*******:********@httpbin.org/delay/2'],
]);
