<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

final class ClinicSettingsPolicy
{
    public function manage(User $actor): bool
    {
        return $actor->checkPermissionTo(Permission::SettingsManage->value);
    }
}
