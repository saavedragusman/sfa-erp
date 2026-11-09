<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->date('effective_date')->unique();
            $table->decimal('rate', 20, 8);
            $table->string('source');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_rate_positive CHECK (rate > 0)');
        DB::statement("ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_source_check CHECK (source IN ('auto', 'manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
