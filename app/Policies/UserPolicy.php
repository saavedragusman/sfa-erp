<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Staff management abilities. They only ask for permissions, never for roles.
 */
final class UserPolicy
{
    public function createStaff(User $actor): bool
    {
        return $actor->checkPermissionTo(Permission::StaffManage->value);
    }

    public function updateStaff(User $actor, User $member): bool
    {
        return $actor->checkPermissionTo(Permission::StaffManage->value);
    }

    public function assignAdminRole(User $actor): bool
    {
        return $actor->checkPermissionTo(Permission::StaffManageAdmins->value);
    }
}
