<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Single catalog of permissions (`module.action`). Roles are only bundles of these.
 */
enum Permission: string
{
    case StaffManage = 'staff.manage';
    case StaffManageAdmins = 'staff.manage_admins';
}
