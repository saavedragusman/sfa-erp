<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('BR-56 audit migrations roll back and migrate again cleanly', function () {
    expect(Artisan::call('migrate:rollback', ['--step' => 2, '--force' => true]))->toBe(0);

    expect(Schema::hasTable('audit_logs'))->toBeFalse()
        ->and(DB::selectOne("SELECT to_regproc('audit_logs_reject_mutation') AS fn")->fn)->toBeNull();

    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0);

    expect(Schema::hasTable('audit_logs'))->toBeTrue()
        ->and(DB::selectOne("SELECT count(*) AS c FROM pg_trigger WHERE tgrelid = 'audit_logs'::regclass AND NOT tgisinternal")->c)->toBe(2);
});
