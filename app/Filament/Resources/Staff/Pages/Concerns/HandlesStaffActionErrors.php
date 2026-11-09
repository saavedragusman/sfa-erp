<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Pages\Concerns;

use App\Exceptions\Domain\DuplicateStaffEmailException;
use App\Exceptions\Domain\InvalidStaffRolesException;
use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Translates the staff Actions' exceptions into Filament notifications.
 */
trait HandlesStaffActionErrors
{
    /**
     * @param  Closure(): Model  $callback
     */
    protected function runStaffAction(Closure $callback): Model
    {
        try {
            return $callback();
        } catch (AuthorizationException) {
            throw $this->failWith('No tienes permiso para realizar esta acción.');
        } catch (InvalidStaffRolesException) {
            throw $this->failWith('Roles no válidos: se necesita al menos un rol de personal y el rol de tutor no se combina con ellos.');
        } catch (DuplicateStaffEmailException) {
            throw $this->failWith('Ya existe un usuario con ese correo.');
        }
    }

    protected function actor(): User
    {
        $actor = auth()->user();

        return $actor instanceof User ? $actor : throw new LogicException('An authenticated user is required.');
    }

    private function failWith(string $message): Halt
    {
        Notification::make()->danger()->title($message)->send();

        return new Halt;
    }
}
