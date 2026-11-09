<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Reception = 'reception';
    case Doctor = 'doctor';
    case Owner = 'owner';
}
