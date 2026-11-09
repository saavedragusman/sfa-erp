<?php

declare(strict_types=1);

namespace App\Support\Staff;

use App\Exceptions\Domain\DuplicateStaffEmailException;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Maps the users.email unique violation (SQLSTATE 23505) to a domain exception.
 * Any other database error is returned untouched.
 */
final class StaffEmailConflict
{
    private const SQLSTATE_UNIQUE_VIOLATION = '23505';

    private const EMAIL_CONSTRAINT = 'users_email_unique';

    public function translate(QueryException $exception, string $email): Throwable
    {
        $isEmailDuplicate = ($exception->errorInfo[0] ?? null) === self::SQLSTATE_UNIQUE_VIOLATION
            && str_contains($exception->getMessage(), self::EMAIL_CONSTRAINT);

        return $isEmailDuplicate ? DuplicateStaffEmailException::forEmail($email) : $exception;
    }
}
