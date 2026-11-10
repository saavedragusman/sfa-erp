<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

function seededPermissions(RoleName $role): array
{
    return Role::findByName($role->value)->permissions->pluck('name')->sort()->values()->all();
}

it('BR-01 seeds roles and permissions on an empty database with model events disabled, as db:seed does', function () {
    // DatabaseSeeder uses WithoutModelEvents, which also silences Spatie's cache-flush listeners.
    Model::withoutEvents(fn () => $this->seed(RolesAndPermissionsSeeder::class));

    expect(seededPermissions(RoleName::Admin))->toBe(collect(Permission::cases())->map(fn (Permission $p): string => $p->value)->sort()->values()->all())
        ->and(seededPermissions(RoleName::Manager))->toContain(Permission::StaffManage->value)->not->toContain(Permission::StaffManageAdmins->value);
});

it('BR-01 db:seed works from the artisan command on an empty database', function () {
    $this->artisan('db:seed')->assertSuccessful();

    expect(Role::count())->toBe(count(RoleName::cases()))->and(User::count())->toBe(0);
});
