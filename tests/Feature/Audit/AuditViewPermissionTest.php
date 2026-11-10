<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Settings/StaffTestHelpers.php';

beforeEach(fn () => seedRoles());

it('BR-60 seeds audit.view only to admin', function () {
    foreach (RoleName::cases() as $role) {
        $has = Role::findByName($role->value)->permissions->pluck('name')->contains(Permission::AuditView->value);

        expect($has)->toBe($role === RoleName::Admin);
    }
});

it('BR-60 denies viewing audit logs without audit.view', function (RoleName $role) {
    $actor = userWithRole($role);

    expect(Gate::forUser($actor)->allows('viewAny', AuditLog::class))->toBeFalse();
})->with([RoleName::Manager, RoleName::Reception, RoleName::Doctor, RoleName::Owner]);

it('BR-60 lets admin view audit logs', function () {
    $admin = userWithRole(RoleName::Admin);

    expect(Gate::forUser($admin)->allows('viewAny', AuditLog::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('view', new AuditLog))->toBeTrue();
});

it('BR-60 denies every write ability on audit logs even for admin', function (string $ability) {
    $admin = userWithRole(RoleName::Admin);

    expect(Gate::forUser($admin)->allows($ability, new AuditLog))->toBeFalse();
})->with(['create', 'update', 'delete', 'restore', 'forceDelete']);

it('BR-60 keeps the finance, staff and settings permissions of the roles unchanged', function () {
    $manager = Role::findByName(RoleName::Manager->value)->permissions->pluck('name');

    expect($manager)->toContain(Permission::StaffManage->value, Permission::ExpensesManage->value)
        ->not->toContain(Permission::SettingsManage->value);
});
