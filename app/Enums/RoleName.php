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

    /**
     * Spanish UI label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Manager => 'Gerente',
            self::Reception => 'Recepción',
            self::Doctor => 'Médico',
            self::Owner => 'Tutor',
        };
    }
}
