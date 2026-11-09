<?php

declare(strict_types=1);

namespace App\Queries\Settings;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Staff = users holding at least one staff role (RoleName::isStaff()). Owners and role-less users are excluded.
 */
final class StaffMembersQuery
{
    /**
     * @return Builder<User>
     */
    public function builder(): Builder
    {
        return User::query()
            ->with('roles')
            ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', RoleName::staffValues()));
    }
}
