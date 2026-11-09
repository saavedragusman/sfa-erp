<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('BR-56 audit migrations roll back and migrate again cleanly', function () {
    // Target the audit migrations by path: settings migrations share the migrations table, so `--step` would be order-dependent.
    foreach (['2026_11_09_180000_block_truncate_on_audit_logs.php', '2026_11_09_170000_create_audit_logs_table.php'] as $file) {
        expect(Artisan::call('migrate:rollback', ['--path' => "database/migrations/{$file}", '--force' => true]))->toBe(0);
    }

    expect(Schema::hasTable('audit_logs'))->toBeFalse()
        ->and(DB::selectOne("SELECT to_regproc('audit_logs_reject_mutation') AS fn")->fn)->toBeNull();

    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0);

    expect(Schema::hasTable('audit_logs'))->toBeTrue()
        ->and(DB::selectOne("SELECT count(*) AS c FROM pg_trigger WHERE tgrelid = 'audit_logs'::regclass AND NOT tgisinternal")->c)->toBe(2);
});
