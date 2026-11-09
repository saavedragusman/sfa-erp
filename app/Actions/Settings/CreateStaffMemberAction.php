<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\DTOs\Settings\StaffMemberData;
use App\Models\User;
use App\Notifications\SetStaffPasswordNotification;
use App\Support\Audit\AuditLogger;
use App\Support\Staff\StaffRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class CreateStaffMemberAction
{
    public function __construct(
        private readonly StaffRoles $roles,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, StaffMemberData $data): User
    {
        $gate = Gate::forUser($actor);
        $gate->authorize('createStaff', User::class);

        if ($this->roles->includesAdmin($data->roles)) {
            $gate->authorize('assignAdminRole', User::class);
        }

        $this->roles->assertValid($data->roles);

        return DB::transaction(function () use ($actor, $data): User {
            // The member defines the real password through the emailed link (BR-59).
            $member = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Str::random(64),
            ]);
            $member->assignRole($this->roles->names($data->roles));

            $token = Password::broker()->createToken($member);

            $this->audit->record($actor, 'staff.created', $member, null, [
                'name' => $member->name,
                'email' => $member->email,
                'roles' => $this->roles->names($data->roles),
            ]);

            DB::afterCommit(fn () => $member->notify(new SetStaffPasswordNotification($token)));

            return $member->load('roles');
        });
    }
}
