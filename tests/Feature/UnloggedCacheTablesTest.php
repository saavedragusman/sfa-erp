<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('keeps the cache and cache_locks tables UNLOGGED', function (string $table) {
    $persistence = DB::selectOne('SELECT relpersistence FROM pg_class WHERE oid = ?::regclass', [$table])->relpersistence;

    expect($persistence)->toBe('u');
})->with(['cache', 'cache_locks']);

it('keeps durable tables logged', function () {
    $persistence = DB::selectOne("SELECT relpersistence FROM pg_class WHERE oid = 'jobs'::regclass")->relpersistence;

    expect($persistence)->toBe('p');
});

it('dispatches database queue jobs only after the transaction commits', function () {
    expect(config('queue.connections.database.after_commit'))->toBeTrue();
});
