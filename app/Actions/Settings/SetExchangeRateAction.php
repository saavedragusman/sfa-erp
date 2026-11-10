<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\DTOs\Settings\ExchangeRateData;
use App\Exceptions\Domain\DuplicateExchangeRateException;
use App\Exceptions\Domain\InvalidExchangeRateException;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Loads the rate of a date, or edits it if that date already has one (BR-05).
 * The "already used by an invoice or expense" lock arrives with those tables (DEC-13).
 */
final class SetExchangeRateAction
{
    private const SQLSTATE_UNIQUE_VIOLATION = '23505';

    private const DECIMALS = 8;

    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(User $actor, ExchangeRateData $data): ExchangeRate
    {
        Gate::forUser($actor)->authorize('manage', ExchangeRate::class);

        $rate = $this->normalize($data->rate);
        $date = $data->effectiveDate->toDateString();

        try {
            return DB::transaction(function () use ($actor, $data, $rate, $date): ExchangeRate {
                $existing = ExchangeRate::query()->whereDate('effective_date', $date)->lockForUpdate()->first();
                $before = $existing === null ? null : $this->snapshot($existing);

                $saved = $existing ?? new ExchangeRate;
                $saved->fill(['effective_date' => $date, 'rate' => $rate, 'source' => $data->source])->save();
                $saved->refresh();

                $this->audit->record($actor, $existing === null ? 'exchange_rate.created' : 'exchange_rate.updated', $saved, $before, $this->snapshot($saved));

                return $saved;
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === self::SQLSTATE_UNIQUE_VIOLATION) {
                throw DuplicateExchangeRateException::forDate($date);
            }

            throw $exception;
        }
    }

    /**
     * Returns the rate as a plain decimal with exactly 8 decimals, or fails when it is not a positive number that fits numeric(20,8).
     */
    private function normalize(string $rate): string
    {
        if (preg_match('/^\d{1,12}(\.\d{1,'.self::DECIMALS.'})?$/', $rate) !== 1) {
            throw InvalidExchangeRateException::forRate($rate);
        }

        try {
            $value = BigDecimal::of($rate);
        } catch (MathException) {
            throw InvalidExchangeRateException::forRate($rate);
        }

        if (! $value->isPositive()) {
            throw InvalidExchangeRateException::forRate($rate);
        }

        return (string) $value->toScale(self::DECIMALS);
    }

    /**
     * @return array{effective_date: string, rate: string, source: string}
     */
    private function snapshot(ExchangeRate $rate): array
    {
        return [
            'effective_date' => $rate->effective_date->toDateString(),
            'rate' => $rate->rate,
            'source' => $rate->source->value,
        ];
    }
}
