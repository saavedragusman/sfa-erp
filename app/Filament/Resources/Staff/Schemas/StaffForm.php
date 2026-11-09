<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Schemas;

use App\Enums\RoleName;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Correo')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(table: 'users', column: 'email', ignoreRecord: true),
            Select::make('roles')
                ->label('Roles')
                ->multiple()
                ->required()
                ->options(fn (): array => self::roleOptions()),
        ]);
    }

    /**
     * Owner is never offered; admin only to users with staff.manage_admins.
     *
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        $canAssignAdmin = Gate::allows('assignAdminRole', User::class);
        $options = [];

        foreach (RoleName::cases() as $role) {
            if (! $role->isStaff() || ($role === RoleName::Admin && ! $canAssignAdmin)) {
                continue;
            }

            $options[$role->value] = $role->label();
        }

        return $options;
    }
}
