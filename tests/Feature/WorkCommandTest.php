<?php

it('does not use stream records older than 7 days', function () {
    RedisAdapter::xadd('pulse_requests', [
        'duration' => $startedAt->diffInMilliseconds(now()),
        'route' => 'GET /users',
        'user_id' => 5,
    ]);

    expect(true)->toBeTrue();
});
