<?php

declare(strict_types=1);

use App\Actions\Settings\CreateStaffMemberAction;
use App\DTOs\Settings\StaffMemberData;
use App\Enums\RoleName;
use App\Exceptions\Domain\InvalidStaffRolesException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/StaffTestHelpers.php';

beforeEach(function () {
    seedRoles();
    Notification::fake();
});

function newStaff(RoleName ...$roles): StaffMemberData
{
    return new StaffMemberData(name: 'Nuevo Personal', email: 'nuevo@example.com', roles: $roles);
}

it('BR-01 forbids users without staff.manage from creating staff', function (RoleName $role) {
    $actor = userWithRole($role);

    expect(fn () => app(CreateStaffMemberAction::class)->execute($actor, newStaff(RoleName::Doctor)))
        ->toThrow(AuthorizationException::class);
})->with([RoleName::Reception, RoleName::Doctor, RoleName::Owner]);

it('BR-01 forbids a user without any role from creating staff', function () {
    expect(fn () => app(CreateStaffMemberAction::class)->execute(User::factory()->create(), newStaff(RoleName::Doctor)))
        ->toThrow(AuthorizationException::class);
});

it('BR-01 forbids manager from assigning the admin role', function () {
    $manager = userWithRole(RoleName::Manager);

    expect(fn () => app(CreateStaffMemberAction::class)->execute($manager, newStaff(RoleName::Doctor, RoleName::Admin)))
        ->toThrow(AuthorizationException::class);
});

it('BR-01 lets manager create non-admin staff with several roles', function () {
    $manager = userWithRole(RoleName::Manager);

    $staff = app(CreateStaffMemberAction::class)->execute($manager, newStaff(RoleName::Reception, RoleName::Doctor));

    expect($staff->roles->pluck('name')->sort()->values()->all())->toBe(['doctor', 'reception'])
        ->and($staff->email)->toBe('nuevo@example.com');
});

it('BR-01 lets admin create staff with the admin role', function () {
    $admin = userWithRole(RoleName::Admin);

    $staff = app(CreateStaffMemberAction::class)->execute($admin, newStaff(RoleName::Admin));

    expect($staff->roles->pluck('name')->all())->toBe(['admin']);
});

it('BR-01 denies an admin who lacks staff.manage_admins', function () {
    $admin = userWithRole(RoleName::Admin);
    Role::findByName('admin')->revokePermissionTo('staff.manage_admins');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => app(CreateStaffMemberAction::class)->execute($admin->fresh(), newStaff(RoleName::Admin)))
        ->toThrow(AuthorizationException::class);
});

it('BR-01 denies an admin who lacks staff.manage', function () {
    $admin = userWithRole(RoleName::Admin);
    Role::findByName('admin')->revokePermissionTo('staff.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => app(CreateStaffMemberAction::class)->execute($admin->fresh(), newStaff(RoleName::Doctor)))
        ->toThrow(AuthorizationException::class);
});

it('BR-01 rejects owner combined with staff roles', function (array $roles) {
    $admin = userWithRole(RoleName::Admin);

    $call = fn () => app(CreateStaffMemberAction::class)->execute($admin, newStaff(...$roles));

    expect($call)->toThrow(InvalidStaffRolesException::class);
    expect(InvalidStaffRolesException::RULE)->toBe('BR-01');
})->with([
    'owner with doctor' => [[RoleName::Owner, RoleName::Doctor]],
    'owner alone' => [[RoleName::Owner]],
    'no roles' => [[]],
]);

it('BR-01 persists nothing when authorization fails', function () {
    $reception = userWithRole(RoleName::Reception);
    $before = ['users' => User::count(), 'audit' => AuditLog::count(), 'tokens' => DB::table('password_reset_tokens')->count()];

    try {
        app(CreateStaffMemberAction::class)->execute($reception, newStaff(RoleName::Doctor));
    } catch (AuthorizationException) {
    }

    expect(User::count())->toBe($before['users'])
        ->and(AuditLog::count())->toBe($before['audit'])
        ->and(DB::table('password_reset_tokens')->count())->toBe($before['tokens']);
});

it('BR-57 audits staff creation with actor and after values', function () {
    $manager = userWithRole(RoleName::Manager);

    $staff = app(CreateStaffMemberAction::class)->execute($manager, newStaff(RoleName::Doctor));

    $log = AuditLog::where('entity_id', $staff->id)->firstOrFail();

    expect($log->action)->toBe('staff.created')
        ->and($log->actor_id)->toBe($manager->id)
        ->and($log->before)->toBeNull()
        ->and($log->after)->toEqual(['name' => 'Nuevo Personal', 'email' => 'nuevo@example.com', 'roles' => ['doctor']]);
});

it('BR-57 rolls back staff creation when the audit entry cannot be written', function () {
    $manager = userWithRole(RoleName::Manager);
    $usersBefore = User::count();

    DB::unprepared("CREATE FUNCTION test_fail_audit() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'audit down'; END; \$\$ LANGUAGE plpgsql");
    DB::unprepared('CREATE TRIGGER test_fail_audit BEFORE INSERT ON audit_logs FOR EACH ROW EXECUTE FUNCTION test_fail_audit()');

    expect(fn () => DB::transaction(fn () => app(CreateStaffMemberAction::class)->execute($manager, newStaff(RoleName::Doctor))))
        ->toThrow(QueryException::class, 'audit down');

    expect(User::count())->toBe($usersBefore);
});
