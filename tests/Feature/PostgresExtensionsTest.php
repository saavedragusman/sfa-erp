<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('enables the PostgreSQL extensions the schema relies on', function () {
    $installed = collect(DB::select('SELECT extname FROM pg_extension'))->pluck('extname')->all();

    expect($installed)->toContain('btree_gist', 'citext', 'pg_trgm');
});

it('runs the extensions migration before every other migration', function () {
    $files = collect(glob(database_path('migrations/*.php')))->map(fn (string $f) => basename($f))->sort()->values();

    expect($files->first())->toContain('enable_postgresql_extensions');
});
