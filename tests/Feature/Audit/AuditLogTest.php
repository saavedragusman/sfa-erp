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
        'nested' => ['api_token' => 'xyz', 'keep' => 'ok'],
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
