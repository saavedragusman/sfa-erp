<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\DTOs\Settings\StaffMemberData;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Staff\StaffRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateStaffMemberAction
{
    public function __construct(
        private readonly StaffRoles $roles,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, User $member, StaffMemberData $data): User
    {
        $gate = Gate::forUser($actor);
        $gate->authorize('updateStaff', $member);

        // Editing or demoting an existing admin, or promoting to admin, needs staff.manage_admins (DEC-11).
        if ($this->roles->holdsAdmin($member) || $this->roles->includesAdmin($data->roles)) {
            $gate->authorize('assignAdminRole', User::class);
        }

        $this->roles->assertMemberIsStaff($member);
        $this->roles->assertValid($data->roles);

        return DB::transaction(function () use ($actor, $member, $data): User {
            $before = $this->snapshot($member);

            $member->update(['name' => $data->name, 'email' => $data->email]);
            $member->syncRoles($this->roles->names($data->roles));
            $member->load('roles');

            $this->audit->record($actor, 'staff.updated', $member, $before, $this->snapshot($member));

            return $member;
        });
    }

    /**
     * @return array{name: string, email: string, roles: list<string>}
     */
    private function snapshot(User $member): array
    {
        return [
            'name' => $member->name,
            'email' => $member->email,
            'roles' => $this->roles->namesOf($member),
        ];
    }
}
