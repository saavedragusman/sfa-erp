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

    /**
     * Single definition of "staff": every role except owner. Panel access, the staff list,
     * the role form and role validation all read it from here.
     */
    public function isStaff(): bool
    {
        return $this !== self::Owner;
    }

    /**
     * @return list<self>
     */
    public static function staff(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role->isStaff()));
    }

    /**
     * @return list<string>
     */
    public static function staffValues(): array
    {
        return array_map(fn (self $role): string => $role->value, self::staff());
    }
}
