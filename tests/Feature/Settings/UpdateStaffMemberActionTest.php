<?php

declare(strict_types=1);

use App\Actions\Settings\UpdateStaffMemberAction;
use App\DTOs\Settings\StaffMemberData;
use App\Enums\RoleName;
use App\Exceptions\Domain\InvalidStaffRolesException;
use App\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
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
