<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use DomainException;

final class DuplicateStaffEmailException extends DomainException
{
    public const RULE = 'BR-01';

    public static function forEmail(string $email): self
    {
        return new self("A user with the email {$email} already exists.");
    }
}
