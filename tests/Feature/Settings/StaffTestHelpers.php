<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

function seedRoles(): void
{
    test()->seed(RolesAndPermissionsSeeder::class);
}

function userWithRole(RoleName ...$roles): User
{
    $user = User::factory()->create();
    $user->assignRole(array_map(fn (RoleName $role): string => $role->value, $roles));

    return $user;
}
