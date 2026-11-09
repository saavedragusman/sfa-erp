<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Settings\BootstrapAdminAction;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Crea un usuario con el rol admin de forma interactiva (primer acceso al sistema)';

    public function handle(BootstrapAdminAction $action): int
    {
        if (! Role::where('name', RoleName::Admin->value)->where('guard_name', config('auth.defaults.guard'))->exists()) {
            $this->error('El rol admin no existe. Ejecuta primero: php artisan db:seed');

            return self::FAILURE;
        }

        $name = text(label: 'Nombre', required: true);
        $email = text(label: 'Correo', required: true);
        $password = password(label: 'Contraseña', required: true);
        $confirmation = password(label: 'Confirmar contraseña', required: true);

        if ($password !== $confirmation) {
            $this->error('La confirmación de la contraseña no coincide.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:'.User::class.',email'],
                'password' => ['required', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $admin = $action->execute($name, $email, $password);

        $this->info("Administrador {$admin->email} creado.");

        return self::SUCCESS;
    }
}
