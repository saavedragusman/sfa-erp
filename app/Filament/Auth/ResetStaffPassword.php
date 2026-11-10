<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Support\Auth\PasswordPolicy;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * Password reset page for the staff panel, with the password requirement shown up front (BR-59).
 */
class ResetStaffPassword extends ResetPassword
{
    protected function getPasswordFormComponent(): Component
    {
        $password = parent::getPasswordFormComponent();

        return $password instanceof TextInput
            ? $password->helperText(PasswordPolicy::hint())
            : $password;
    }
}
