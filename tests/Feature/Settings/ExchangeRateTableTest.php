<?php

declare(strict_types=1);

use App\Enums\ExchangeRateSource;
use App\Models\ExchangeRate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function insertRate(array $overrides = []): void
{
    // A nested transaction is a savepoint, so a rejected row does not poison the test transaction.
    DB::transaction(fn () => DB::table('exchange_rates')->insert(array_merge([
        'effective_date' => '2026-11-09',
        'rate' => '36.50000000',
        'source' => 'manual',
    ], $overrides)));
}

function sqlStateOf(Closure $statement): ?string
{
    try {
        $statement();
    } catch (QueryException $exception) {
        return (string) $exception->getCode();
    }

    return null;
}

it('BR-05 rejects a second rate for the same date', function () {
    insertRate();

    expect(sqlStateOf(fn () => insertRate(['rate' => '40'])))->toBe('23505');
});

it('BR-05 rejects a zero or negative rate', function (string $rate) {
    expect(sqlStateOf(fn () => insertRate(['rate' => $rate])))->toBe('23514');
})->with(['0', '-1', '-0.00000001']);

it('BR-05 rejects an unknown source', function () {
    expect(sqlStateOf(fn () => insertRate(['source' => 'whatsapp'])))->toBe('23514');
});

it('BR-05 accepts auto and manual rates on different dates', function () {
    insertRate(['effective_date' => '2026-11-08', 'source' => 'auto']);
    insertRate(['effective_date' => '2026-11-09', 'source' => 'manual']);

    expect(ExchangeRate::count())->toBe(2)
        ->and(ExchangeRate::where('effective_date', '2026-11-08')->sole()->source)->toBe(ExchangeRateSource::Auto);
});

it('BR-05 reads the rate as an exact string, never a float', function () {
    insertRate(['rate' => '36.12345678']);

    expect(ExchangeRate::sole()->rate)->toBe('36.12345678');
});
