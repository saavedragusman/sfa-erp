<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function financePermissions(): array
{
    return [
        Permission::FinanceViewGlobal->value,
        Permission::ExpensesManage->value,
        Permission::PayrollManage->value,
        Permission::CashViewAll->value,
    ];
}

function grantedTo(RoleName $role): array
{
    return Role::findByName($role->value)->permissions->pluck('name')->all();
}

it('BR-02 declares the four finance permissions in the catalog', function () {
    expect(financePermissions())->toBe(['finance.view_global', 'expenses.manage', 'payroll.manage', 'cash.view_all']);
});

it('BR-02 seeds finance permissions only to admin and manager', function () {
    foreach ([RoleName::Admin, RoleName::Manager] as $role) {
        expect(grantedTo($role))->toContain(...financePermissions());
    }
});

it('BR-02 grants no finance permission to reception, doctor or owner', function () {
    foreach ([RoleName::Reception, RoleName::Doctor, RoleName::Owner] as $role) {
        expect(array_intersect(grantedTo($role), financePermissions()))->toBe([]);
    }
});

it('BR-02 keeps the BR-01 and settings permissions unchanged', function () {
    expect(grantedTo(RoleName::Manager))->toContain(Permission::StaffManage->value)
        ->not->toContain(Permission::StaffManageAdmins->value)
        ->not->toContain(Permission::SettingsManage->value)
        ->and(grantedTo(RoleName::Admin))->toContain(Permission::StaffManage->value, Permission::StaffManageAdmins->value, Permission::SettingsManage->value);
});
