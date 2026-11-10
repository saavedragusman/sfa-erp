<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Role => permission bundle. Later business rules extend this single map.
     *
     * @return array<string, list<Permission>>
     */
    private function bundles(): array
    {
        return [
            RoleName::Admin->value => [Permission::StaffManage, Permission::StaffManageAdmins],
            RoleName::Manager->value => [Permission::StaffManage],
            RoleName::Reception->value => [],
            RoleName::Doctor->value => [],
            RoleName::Owner->value => [],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard');

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, $guard);
        }

        // Spatie flushes its cache from model events, and DatabaseSeeder silences them (WithoutModelEvents).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->bundles() as $role => $permissions) {
            Role::findOrCreate($role, $guard)
                ->syncPermissions(array_map(fn (Permission $permission): string => $permission->value, $permissions));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
