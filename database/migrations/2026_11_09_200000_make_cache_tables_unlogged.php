<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cache data is disposable: skipping the WAL makes writes cheaper and losing it after a crash is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cache SET UNLOGGED');
        DB::statement('ALTER TABLE cache_locks SET UNLOGGED');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cache SET LOGGED');
        DB::statement('ALTER TABLE cache_locks SET LOGGED');
    }
};
