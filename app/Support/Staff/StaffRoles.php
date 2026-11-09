<?php

declare(strict_types=1);

namespace App\Support\Staff;

use App\Enums\RoleName;
use App\Exceptions\Domain\InvalidStaffRolesException;
use App\Models\User;

/**
 * Role rules shared by the staff Actions (BR-01).
 */
final class StaffRoles
{
    /**
     * @param  list<RoleName>  $roles
     */
    public function includesAdmin(array $roles): bool
    {
        return in_array(RoleName::Admin, $roles, true);
    }

    /**
     * Reads the member's role data; it is not an authorization decision on the actor.
     */
    public function holdsAdmin(User $member): bool
    {
        return $member->roles->contains('name', RoleName::Admin->value);
    }

    /**
     * @param  list<RoleName>  $roles
     */
    public function assertValid(array $roles): void
    {
        if ($roles === []) {
            throw InvalidStaffRolesException::empty();
        }

        if (in_array(RoleName::Owner, $roles, true)) {
            throw InvalidStaffRolesException::ownerCombined();
        }
    }

    public function assertMemberIsStaff(User $member): void
    {
        if ($member->roles->contains('name', RoleName::Owner->value)) {
            throw InvalidStaffRolesException::targetIsOwner();
        }
    }

    /**
     * @param  list<RoleName>  $roles
     * @return list<string>
     */
    public function names(array $roles): array
    {
        $names = array_map(fn (RoleName $role): string => $role->value, $roles);
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    public function namesOf(User $member): array
    {
        /** @var list<string> $names */
        $names = $member->roles->pluck('name')->sort()->values()->all();

        return $names;
    }
}
