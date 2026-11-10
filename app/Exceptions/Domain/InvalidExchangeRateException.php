<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use DomainException;

final class InvalidExchangeRateException extends DomainException
{
    public const RULE = 'BR-05';

    public static function forRate(string $rate): self
    {
        return new self("The exchange rate must be greater than zero with at most 8 decimals: \"{$rate}\".");
    }
}
