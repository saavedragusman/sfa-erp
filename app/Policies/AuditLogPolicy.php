<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Audit logs are read-only for everyone (BR-56, BR-60). Filament allows any ability a Policy
 * does not define, so every write ability is declared and denied here.
 */
final class AuditLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo(Permission::AuditView->value);
    }

    public function view(User $actor, AuditLog $log): bool
    {
        return $actor->checkPermissionTo(Permission::AuditView->value);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $actor, AuditLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, AuditLog $log): bool
    {
        return false;
    }

    public function forceDelete(User $actor, AuditLog $log): bool
    {
        return false;
    }
}
