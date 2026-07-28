<?php

use Laravel\Pulse\Recorders\SlowOutgoingRequests;

it('can normalize URLs', function (string $given, string $expected) {
    expect(SlowOutgoingRequests::normalizeUrl($given))->toBe($expected);
})->with([
    ['https://httpbin.org/delay/2', 'https://httpbin.org/delay/2'],
    ['https://laravel@httpbin.org/delay/2', 'https://@httpbin.org/delay/2'],
    ['https://laravel:password@httpbin.org/delay/2', 'https://@httpbin.org/delay/2'],
]);
