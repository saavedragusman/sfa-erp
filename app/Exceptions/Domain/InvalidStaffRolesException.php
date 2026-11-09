<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use DomainException;

final class InvalidStaffRolesException extends DomainException
{
    public const RULE = 'BR-01';

    public static function empty(): self
    {
        return new self('Staff needs at least one role.');
    }

    public static function ownerCombined(): self
    {
        return new self('The owner role is never combined with staff roles.');
    }

    public static function targetIsOwner(): self
    {
        return new self('An owner account is not staff and cannot be edited as staff.');
    }
}
