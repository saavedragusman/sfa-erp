<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Queries\Settings\AuditLogsQuery;
use App\Support\Audit\AuditActionLabel;
use Illuminate\Support\Facades\DB;

function logRow(array $overrides = []): AuditLog
{
    return AuditLog::create(array_merge([
        'actor_id' => null,
        'action' => 'staff.created',
        'entity_type' => 'user',
        'entity_id' => 1,
    ], $overrides));
}

it('BR-60 lists audit logs newest first with their actor', function () {
    $actor = User::factory()->create(['name' => 'Ana Admin']);
    $first = logRow(['actor_id' => $actor->id]);
    $second = logRow(['action' => 'settings.updated']);

    $logs = app(AuditLogsQuery::class)->builder()->get();

    expect($logs->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($logs->last()->actor->name)->toBe('Ana Admin')
        ->and($logs->first()->actor)->toBeNull();
});

it('BR-60 loads the actors without one query per row', function () {
    $actor = User::factory()->create();
    foreach (range(1, 5) as $i) {
        logRow(['actor_id' => $actor->id]);
    }

    DB::enableQueryLog();
    app(AuditLogsQuery::class)->builder()->get()->each(fn (AuditLog $log) => $log->actor?->name);

    expect(DB::getQueryLog())->toHaveCount(2);
});

it('BR-60 labels known actions in Spanish and falls back to the key', function () {
    $label = new AuditActionLabel;

    expect($label->for('staff.created'))->toBe('Personal creado')
        ->and($label->for('exchange_rate.updated'))->toBe('Tasa de cambio editada')
        ->and($label->for('something.new'))->toBe('something.new');
});

it('BR-60 keeps an index for each audit filter', function () {
    $indexes = collect(DB::select("SELECT indexdef FROM pg_indexes WHERE tablename = 'audit_logs'"))->pluck('indexdef')->implode("\n");

    expect($indexes)->toContain('(created_at)')
        ->and($indexes)->toContain('(actor_id)')
        ->and($indexes)->toContain('(action)');
});
