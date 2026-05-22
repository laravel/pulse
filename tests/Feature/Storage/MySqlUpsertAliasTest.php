<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Storage\DatabaseStorage;

describe('MySQL aggregate upserts and use_upsert_alias', function () {
    beforeEach(function () {
        skipUnlessMySql();

        App::make(DatabaseStorage::class)->purge();
    });

    dataset('upsert alias modes', [
        'before (legacy values() syntax)' => [false, 'values(`value`)', 'laravel_upsert_alias'],
        'after (row alias syntax)' => [true, 'laravel_upsert_alias', 'values(`value`)'],
    ]);

    it('ingests count aggregates and emits the expected upsert SQL', function (bool $useUpsertAlias, string $expectedFragment, string $forbiddenFragment) {
        configureUpsertAlias($useUpsertAlias);

        $aggregateQueries = collect();

        DB::listen(function (QueryExecuted $event) use (&$aggregateQueries) {
            if (str_contains($event->sql, 'pulse_aggregates') && str_contains(strtolower($event->sql), 'on duplicate')) {
                $aggregateQueries->push($event->sql);
            }
        });

        Pulse::record('upsert_alias_test', 'test-key', 1)->count();
        expect(Pulse::ingest())->toBe(1);

        Pulse::record('upsert_alias_test', 'test-key', 1)->count();
        expect(Pulse::ingest())->toBe(1);

        expect($aggregateQueries)->not->toBeEmpty();

        $sql = $aggregateQueries->last();

        expect($sql)->toContain($expectedFragment);
        expect($sql)->not->toContain($forbiddenFragment);

        if ($useUpsertAlias) {
            expect($sql)->toContain('as laravel_upsert_alias');
            expect($sql)->toContain('`pulse_aggregates`');
        } else {
            expect($sql)->not->toContain('as laravel_upsert_alias');
        }

        $stored = Pulse::ignore(fn () => DB::table('pulse_aggregates')
            ->where('type', 'upsert_alias_test')
            ->where('aggregate', 'count')
            ->where('key', 'test-key')
            ->where('period', 60)
            ->value('value'));

        expect((int) $stored)->toBe(2);
    })->with('upsert alias modes');

    it('ingests min, max, sum, and avg aggregates when use_upsert_alias is enabled', function () {
        configureUpsertAlias(true);

        Pulse::record('upsert_alias_test', 'avg-key', 100)->count()->min()->max()->sum()->avg();
        Pulse::record('upsert_alias_test', 'avg-key', 300)->count()->min()->max()->sum()->avg();
        expect(Pulse::ingest())->toBe(2);

        $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')
            ->where('type', 'upsert_alias_test')
            ->where('key', 'avg-key')
            ->where('period', 60)
            ->pluck('value', 'aggregate'));

        expect((int) ($aggregates['count'] ?? 0))->toBe(2);
        expect((int) ($aggregates['min'] ?? 0))->toBe(100);
        expect((int) ($aggregates['max'] ?? 0))->toBe(300);
        expect((int) ($aggregates['sum'] ?? 0))->toBe(400);
        expect((int) ($aggregates['avg'] ?? 0))->toBe(200);
    });

    it('documents the pre-fix failure when row alias is mixed with values()', function () {
        configureUpsertAlias(true);

        $brokenSql = <<<'SQL'
            insert into `pulse_aggregates` (`aggregate`, `bucket`, `key`, `period`, `type`, `value`)
            values ('count', 1, 'key', 60, 'broken_test', 1)
            as laravel_upsert_alias
            on duplicate key update `value` = `value` + values(`value`)
            SQL;

        try {
            expect(fn () => DB::statement($brokenSql))
                ->toThrow(QueryException::class);
        } finally {
            Pulse::flush();
        }
    });
});
