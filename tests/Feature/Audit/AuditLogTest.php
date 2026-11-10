<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function recordAudit(?User $actor, User $entity, ?array $before, ?array $after): AuditLog
{
    return app(AuditLogger::class)->record($actor, 'staff.updated', $entity, $before, $after);
}

it('BR-56 rejects updating or deleting an audit log row', function () {
    $user = User::factory()->create();
    $log = recordAudit($user, $user, null, ['name' => 'A']);

    // A savepoint per attempt keeps the PostgreSQL transaction usable after each rejection.
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered'])))
        ->toThrow(QueryException::class, 'append-only')
        ->and(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('id', $log->id)->delete()))
        ->toThrow(QueryException::class, 'append-only');

    expect(DB::table('audit_logs')->where('id', $log->id)->value('action'))->toBe('staff.updated');
});

it('BR-56 never stores passwords or tokens', function () {
    $user = User::factory()->create();

    $log = recordAudit($user, $user, null, [
        'name' => 'A',
        'password' => 'secret',
        'remember_token' => 'abc',
        'password_confirmation' => 'secret-again',
        'nested' => ['token' => 'xyz', 'keep' => 'ok'],
    ]);

    $stored = json_encode(DB::table('audit_logs')->where('id', $log->id)->first());

    expect($stored)->not->toContain('secret')
        ->not->toContain('abc')
        ->not->toContain('xyz')
        ->toContain('keep');
});

it('BR-57 audit logger records actor, ip and before/after values in the caller transaction', function () {
    app()->instance('request', Request::create('/', 'GET', server: ['REMOTE_ADDR' => '10.1.2.3']));
    $actor = User::factory()->create();
    $entity = User::factory()->create();

    $log = recordAudit($actor, $entity, ['name' => 'Old'], ['name' => 'New'])->fresh();

    expect($log->actor_id)->toBe($actor->id)
        ->and($log->action)->toBe('staff.updated')
        ->and($log->entity_type)->toBe($entity->getMorphClass())
        ->and($log->entity_id)->toBe($entity->id)
        ->and($log->ip)->toBe('10.1.2.3')
        ->and($log->before)->toBe(['name' => 'Old'])
        ->and($log->after)->toBe(['name' => 'New']);
});

it('BR-57 audit logger row disappears when the caller transaction rolls back', function () {
    $entity = User::factory()->create();

    try {
        DB::transaction(function () use ($entity) {
            recordAudit(null, $entity, null, ['name' => 'X']);

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(AuditLog::count())->toBe(0);
});

it('BR-56 rejects truncating the audit log table', function () {
    $user = User::factory()->create();
    recordAudit($user, $user, null, ['name' => 'A']);

    expect(fn () => DB::transaction(fn () => DB::unprepared('TRUNCATE TABLE audit_logs')))
        ->toThrow(QueryException::class, 'append-only');

    expect(AuditLog::count())->toBe(1);
});

it('BR-56 never stores secrets', function () {
    $user = User::factory()->create();

    $log = recordAudit($user, $user, null, [
        'secret' => 'top-secret-value',
        'Secret' => 'upper-case-key',
        'api_key' => 'key-value',
        'hash' => 'plain-hash',
        'file_hash' => 'suffix-hash',
        'nested' => ['API_KEY' => 'deep-secret', 'keep' => 'ok'],
    ]);

    $stored = json_encode(DB::table('audit_logs')->where('id', $log->id)->first());

    expect($stored)->not->toContain('top-secret-value')
        ->not->toContain('upper-case-key')
        ->not->toContain('deep-secret')
        ->not->toContain('key-value')
        ->not->toContain('plain-hash')
        ->not->toContain('suffix-hash')
        ->toContain('keep');
});

it('BR-56 stores legitimate fields that only contain redacted words as part of their name', function () {
    $user = User::factory()->create();

    $log = recordAudit($user, $user, null, [
        'password_changed_at' => '2026-11-09',
        'token_count' => 3,
        'secretary_name' => 'Marta',
        'hashtag' => '#vet',
        'api_key_label' => 'main',
    ]);

    // jsonb does not preserve key order, so compare as an unordered map.
    expect($log->fresh()->after)->toEqual([
        'password_changed_at' => '2026-11-09',
        'token_count' => 3,
        'secretary_name' => 'Marta',
        'hashtag' => '#vet',
        'api_key_label' => 'main',
    ]);
});

it('BR-56 omits the hidden attributes of the audited model', function () {
    $user = User::factory()->create()->makeHidden(['email']);

    $log = recordAudit($user, $user, ['email' => 'old@example.com', 'name' => 'Old'], ['email' => 'new@example.com', 'name' => 'New']);

    expect($log->fresh()->before)->toBe(['name' => 'Old'])
        ->and($log->fresh()->after)->toBe(['name' => 'New']);
});
