<?php

declare(strict_types=1);

namespace App\DTOs\Settings;

use App\Enums\ExchangeRateSource;
use Carbon\CarbonImmutable;

final readonly class ExchangeRateData
{
    /**
     * @param  string  $rate  Exact decimal string (VES per USD); never a float.
     */
    public function __construct(
        public CarbonImmutable $effectiveDate,
        public string $rate,
        public ExchangeRateSource $source,
    ) {}
}
