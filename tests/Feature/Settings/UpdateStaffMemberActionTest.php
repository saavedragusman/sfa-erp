<?php

declare(strict_types=1);

use App\Actions\Settings\UpdateStaffMemberAction;
use App\DTOs\Settings\StaffMemberData;
use App\Enums\RoleName;
use App\Exceptions\Domain\DuplicateStaffEmailException;
use App\Exceptions\Domain\InvalidStaffRolesException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/StaffTestHelpers.php';

beforeEach(function () {
    seedRoles();
    Notification::fake();
});

function changes(string $name, RoleName ...$roles): StaffMemberData
{
    return new StaffMemberData(name: $name, email: 'changed@example.com', roles: $roles);
}

it('BR-01 forbids users without staff.manage from updating staff', function (RoleName $role) {
    $actor = userWithRole($role);
    $member = userWithRole(RoleName::Doctor);

    expect(fn () => app(UpdateStaffMemberAction::class)->execute($actor, $member, changes('X', RoleName::Doctor)))
        ->toThrow(AuthorizationException::class);

    expect($member->fresh()->name)->not->toBe('X');
})->with([RoleName::Reception, RoleName::Doctor, RoleName::Owner]);

it('BR-01 forbids manager from editing an existing admin', function () {
    $manager = userWithRole(RoleName::Manager);
    $admin = userWithRole(RoleName::Admin);

    expect(fn () => app(UpdateStaffMemberAction::class)->execute($manager, $admin, changes('Renamed', RoleName::Admin)))
        ->toThrow(AuthorizationException::class);

    expect($admin->fresh()->name)->not->toBe('Renamed')
        ->and($admin->fresh()->email)->not->toBe('changed@example.com');
});

it('BR-01 forbids manager from demoting an existing admin', function () {
    $manager = userWithRole(RoleName::Manager);
    $admin = userWithRole(RoleName::Admin);

    expect(fn () => app(UpdateStaffMemberAction::class)->execute($manager, $admin, changes('Same', RoleName::Doctor)))
        ->toThrow(AuthorizationException::class);

    expect($admin->fresh()->roles->pluck('name')->all())->toBe(['admin']);
});

it('BR-01 forbids manager from promoting staff to admin', function () {
    $manager = userWithRole(RoleName::Manager);
    $doctor = userWithRole(RoleName::Doctor);

    expect(fn () => app(UpdateStaffMemberAction::class)->execute($manager, $doctor, changes('Promoted', RoleName::Doctor, RoleName::Admin)))
        ->toThrow(AuthorizationException::class);

    expect($doctor->fresh()->roles->pluck('name')->all())->toBe(['doctor']);
});

it('BR-01 lets admin edit and demote an existing admin', function () {
    $admin = userWithRole(RoleName::Admin);
    $other = userWithRole(RoleName::Admin);

    app(UpdateStaffMemberAction::class)->execute($admin, $other, changes('Demoted', RoleName::Manager));

    expect($other->fresh()->name)->toBe('Demoted')
        ->and($other->fresh()->roles->pluck('name')->all())->toBe(['manager']);
});

it('BR-01 lets manager update non-admin staff and replaces their roles', function () {
    $manager = userWithRole(RoleName::Manager);
    $member = userWithRole(RoleName::Doctor);

    app(UpdateStaffMemberAction::class)->execute($manager, $member, changes('Updated', RoleName::Reception));

    expect($member->fresh()->name)->toBe('Updated')
        ->and($member->fresh()->email)->toBe('changed@example.com')
        ->and($member->fresh()->roles->pluck('name')->all())->toBe(['reception']);
});

it('BR-01 refuses to update an owner as staff or give a staff member the owner role', function () {
    $admin = userWithRole(RoleName::Admin);
    $owner = userWithRole(RoleName::Owner);
    $doctor = userWithRole(RoleName::Doctor);

    expect(fn () => app(UpdateStaffMemberAction::class)->execute($admin, $owner, changes('X', RoleName::Doctor)))
        ->toThrow(InvalidStaffRolesException::class)
        ->and(fn () => app(UpdateStaffMemberAction::class)->execute($admin, $doctor, changes('X', RoleName::Doctor, RoleName::Owner)))
        ->toThrow(InvalidStaffRolesException::class);

    expect($owner->fresh()->roles->pluck('name')->all())->toBe(['owner']);
});

it('BR-57 audits staff updates with before and after values', function () {
    $manager = userWithRole(RoleName::Manager);
    $member = userWithRole(RoleName::Doctor);
    $oldName = $member->name;
    $oldEmail = $member->email;

    app(UpdateStaffMemberAction::class)->execute($manager, $member, changes('Updated', RoleName::Reception));

    $log = AuditLog::where('entity_id', $member->id)->where('action', 'staff.updated')->firstOrFail();

    expect($log->actor_id)->toBe($manager->id)
        ->and($log->before)->toEqual(['name' => $oldName, 'email' => $oldEmail, 'roles' => ['doctor']])
        ->and($log->after)->toEqual(['name' => 'Updated', 'email' => 'changed@example.com', 'roles' => ['reception']]);
});

it('BR-01 rejects updating staff to an email that belongs to another user', function () {
    $manager = userWithRole(RoleName::Manager);
    $member = userWithRole(RoleName::Doctor);
    User::factory()->create(['email' => 'changed@example.com']);
    $auditBefore = AuditLog::count();

    expect(fn () => app(UpdateStaffMemberAction::class)->execute($manager, $member, changes('Renamed', RoleName::Reception)))
        ->toThrow(DuplicateStaffEmailException::class);

    expect($member->fresh()->name)->not->toBe('Renamed')
        ->and($member->fresh()->roles->pluck('name')->all())->toBe(['doctor'])
        ->and(AuditLog::count())->toBe($auditBefore);
});

it('BR-01 lets staff keep their own email when updating', function () {
    $manager = userWithRole(RoleName::Manager);
    $member = userWithRole(RoleName::Doctor);

    app(UpdateStaffMemberAction::class)->execute($manager, $member, new StaffMemberData(name: 'Same Email', email: $member->email, roles: [RoleName::Doctor]));

    expect($member->fresh()->name)->toBe('Same Email');
});

it('BR-57 rolls back staff update when the audit entry cannot be written', function () {
    $manager = userWithRole(RoleName::Manager);
    $member = userWithRole(RoleName::Doctor);
    $originalName = $member->name;

    DB::unprepared("CREATE FUNCTION test_fail_audit() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'audit down'; END; \$\$ LANGUAGE plpgsql");
    DB::unprepared('CREATE TRIGGER test_fail_audit BEFORE INSERT ON audit_logs FOR EACH ROW EXECUTE FUNCTION test_fail_audit()');

    expect(fn () => DB::transaction(fn () => app(UpdateStaffMemberAction::class)->execute($manager, $member, changes('Renamed', RoleName::Reception))))
        ->toThrow(QueryException::class, 'audit down');

    expect($member->fresh()->name)->toBe($originalName)
        ->and($member->fresh()->roles->pluck('name')->all())->toBe(['doctor']);
});
