<?php

declare(strict_types=1);

use App\Actions\Settings\SetExchangeRateAction;
use App\DTOs\Settings\ExchangeRateData;
use App\Enums\ExchangeRateSource;
use App\Enums\RoleName;
use App\Exceptions\Domain\DuplicateExchangeRateException;
use App\Exceptions\Domain\InvalidExchangeRateException;
use App\Models\AuditLog;
use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/StaffTestHelpers.php';

beforeEach(fn () => seedRoles());

function rateData(string $rate = '36.50', string $date = '2026-11-09', ExchangeRateSource $source = ExchangeRateSource::Manual): ExchangeRateData
{
    return new ExchangeRateData(CarbonImmutable::parse($date), $rate, $source);
}

it('BR-05 forbids loading a rate without exchange_rates.manage', function (RoleName $role) {
    $actor = userWithRole($role);

    expect(fn () => app(SetExchangeRateAction::class)->execute($actor, rateData()))->toThrow(AuthorizationException::class);

    expect(ExchangeRate::count())->toBe(0)
        ->and(AuditLog::where('action', 'like', 'exchange_rate.%')->count())->toBe(0);
})->with([RoleName::Reception, RoleName::Doctor, RoleName::Owner]);

it('BR-05 lets admin and manager load a rate', function (RoleName $role) {
    $rate = app(SetExchangeRateAction::class)->execute(userWithRole($role), rateData('36.5'));

    expect($rate->rate)->toBe('36.50000000')
        ->and($rate->source)->toBe(ExchangeRateSource::Manual)
        ->and($rate->effective_date->toDateString())->toBe('2026-11-09');
})->with([RoleName::Admin, RoleName::Manager]);

it('BR-05 creates then edits the rate of a date and audits actor, ip and before/after', function () {
    $actor = userWithRole(RoleName::Manager);
    $action = app(SetExchangeRateAction::class);

    $action->execute($actor, rateData('36.50'));
    $action->execute($actor, rateData('37.25'));

    expect(ExchangeRate::count())->toBe(1)
        ->and(ExchangeRate::sole()->rate)->toBe('37.25000000');

    $created = AuditLog::where('action', 'exchange_rate.created')->sole();
    $updated = AuditLog::where('action', 'exchange_rate.updated')->sole();

    expect($created->actor_id)->toBe($actor->id)
        ->and($created->before)->toBeNull()
        ->and($created->after['rate'])->toBe('36.50000000')
        ->and($updated->before['rate'])->toBe('36.50000000')
        ->and($updated->after['rate'])->toBe('37.25000000')
        ->and($updated->entity_id)->toBe(ExchangeRate::sole()->id);
});

it('BR-05 rolls back the rate when the audit fails', function () {
    $actor = userWithRole(RoleName::Admin);

    AuditLog::creating(fn () => throw new RuntimeException('audit down'));

    expect(fn () => app(SetExchangeRateAction::class)->execute($actor, rateData()))->toThrow(RuntimeException::class);

    expect(ExchangeRate::count())->toBe(0);
});

it('BR-05 rejects a non-positive, malformed or over-precise rate', function (string $rate) {
    $actor = userWithRole(RoleName::Admin);

    expect(fn () => app(SetExchangeRateAction::class)->execute($actor, rateData($rate)))->toThrow(InvalidExchangeRateException::class);

    expect(ExchangeRate::count())->toBe(0);
})->with(['0', '-5', 'abc', '', '36.123456789', '1e3', '99999999999999.5']);

it('BR-05 accepts up to 8 decimals', function () {
    $rate = app(SetExchangeRateAction::class)->execute(userWithRole(RoleName::Admin), rateData('36.12345678'));

    expect($rate->rate)->toBe('36.12345678');
});

it('BR-05 maps a concurrent duplicate date to DuplicateExchangeRateException', function () {
    $actor = userWithRole(RoleName::Admin);

    // Another request commits the same date between this Action's lookup and its insert.
    ExchangeRate::creating(function () {
        DB::table('exchange_rates')->insert(['effective_date' => '2026-11-09', 'rate' => '40', 'source' => 'manual']);
    });

    expect(fn () => app(SetExchangeRateAction::class)->execute($actor, rateData()))->toThrow(DuplicateExchangeRateException::class);
});
