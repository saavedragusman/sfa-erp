<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Creates an admin without an acting user. It is only reachable from the
 * `app:create-admin` console command, which needs shell access to the server.
 */
final class BootstrapAdminAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            $admin = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            $admin->assignRole(RoleName::Admin->value);

            $this->audit->record(null, 'staff.created', $admin, null, [
                'name' => $name,
                'email' => $email,
                'roles' => [RoleName::Admin->value],
            ]);

            return $admin;
        });
    }
}
