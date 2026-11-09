<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function permissionsOf(RoleName $role): array
{
    return Role::findByName($role->value)->permissions->pluck('name')->sort()->values()->all();
}

it('BR-01 seeds staff.manage to admin and manager', function () {
    expect(permissionsOf(RoleName::Admin))->toContain(Permission::StaffManage->value)
        ->and(permissionsOf(RoleName::Manager))->toContain(Permission::StaffManage->value);
});

it('BR-01 seeds staff.manage_admins only to admin', function () {
    expect(permissionsOf(RoleName::Admin))->toContain(Permission::StaffManageAdmins->value);

    foreach ([RoleName::Manager, RoleName::Reception, RoleName::Doctor, RoleName::Owner] as $role) {
        expect(permissionsOf($role))->not->toContain(Permission::StaffManageAdmins->value);
    }
});

it('BR-01 grants no staff permission to reception, doctor or owner', function () {
    foreach ([RoleName::Reception, RoleName::Doctor, RoleName::Owner] as $role) {
        expect(permissionsOf($role))->toBe([]);
    }
});

it('BR-01 keeps the seeder idempotent', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(count(RoleName::cases()))
        ->and(permissionsOf(RoleName::Admin))->toBe([
            Permission::StaffManage->value,
            Permission::StaffManageAdmins->value,
        ]);
});

it('BR-01 keeps user factory and password hashing unchanged', function () {
    $user = User::factory()->create(['password' => 'secret-pass']);

    expect($user->fresh()->password)->not->toBe('secret-pass')
        ->and(password_verify('secret-pass', $user->fresh()->password))->toBeTrue()
        ->and($user->roles)->toBeEmpty();
});
