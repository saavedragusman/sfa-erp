<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExchangeRateSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $effective_date
 * @property string $rate
 * @property ExchangeRateSource $source
 *
 * One VES-per-USD rate per effective date (BR-05). `rate` is an exact decimal string, never a float.
 */
class ExchangeRate extends Model
{
    /** @var list<string> */
    protected $fillable = ['effective_date', 'rate', 'source'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'rate' => 'decimal:8',
            'source' => ExchangeRateSource::class,
        ];
    }
}
