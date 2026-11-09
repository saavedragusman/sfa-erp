<?php

declare(strict_types=1);

namespace App\Notifications;

use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LogicException;

/**
 * Link for new staff to define their password on the staff panel (BR-59).
 */
final class SetStaffPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(CanResetPassword $notifiable): MailMessage
    {
        $panel = Filament::getPanel('vet') ?? throw new LogicException('The vet panel is not registered.');
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Define tu contraseña')
            ->line('Se creó tu cuenta en el sistema de la clínica. Define tu contraseña para ingresar.')
            ->action('Definir contraseña', $panel->getResetPasswordUrl($this->token, $notifiable))
            ->line("El enlace expira en {$minutes} minutos.");
    }
}
