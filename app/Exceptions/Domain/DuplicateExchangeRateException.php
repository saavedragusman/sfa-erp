<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use DomainException;

final class DuplicateExchangeRateException extends DomainException
{
    public const RULE = 'BR-05';

    public static function forDate(string $date): self
    {
        return new self("An exchange rate for {$date} already exists.");
    }
}
