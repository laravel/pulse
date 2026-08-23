<?php

use Laravel\Pulse\Recorders\SlowOutgoingRequests;

it('can normalize URLs', function (string $given, string $expected) {
    expect(SlowOutgoingRequests::normalizeUrl($given))->toBe($expected);
})->with([
    ['https://httpbin.org/delay/2', 'https://httpbin.org/delay/2'],
    ['https://laravel@httpbin.org/delay/2', 'https://@httpbin.org/delay/2'],
    ['https://laravel:password@httpbin.org/delay/2', 'https://@httpbin.org/delay/2'],
]);

it('strips user info from URLs that cannot be parsed instead of throwing', function () {
    expect(SlowOutgoingRequests::normalizeUrl("https://laravel:password@httpbin.org/delay/2\n"))
        ->toBe('https://httpbin.org/delay/2'.PHP_EOL)
        ->and(SlowOutgoingRequests::normalizeUrl("https://httpbin.org/delay/2\n"))
        ->toBe('https://httpbin.org/delay/2'.PHP_EOL);
});
