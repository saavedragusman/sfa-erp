<?php

declare(strict_types=1);

use App\Actions\Settings\UpdateClinicSettingsAction;
use App\DTOs\Settings\ClinicSettingsData;
use App\Enums\Permission;
use App\Enums\RoleName;
use App\Filament\Pages\ManageClinicSettings;
use App\Models\AuditLog;
use App\Settings\ClinicSettings;
use App\Support\Branding\ClinicBranding;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/StaffTestHelpers.php';

beforeEach(fn () => seedRoles());

function clinicData(array $overrides = []): ClinicSettingsData
{
    return new ClinicSettingsData(...array_merge([
        'name' => 'Clínica Nueva',
        'rif' => 'J-12345678-9',
        'address' => 'Av. 5 de Julio',
        'phone' => '0261-0000000',
        'primaryColor' => '#0ea5e9',
        'timezone' => 'America/Caracas',
        'logoPath' => null,
    ], $overrides));
}

it('seeds the clinic settings with the seed clinic defaults', function () {
    $settings = app(ClinicSettings::class);

    expect($settings->name)->toBe('Servicios Veterinarios San Francisco de Asís')
        ->and($settings->timezone)->toBe('America/Caracas')
        ->and($settings->primary_color)->toMatch('/^#[0-9a-f]{6}$/i')
        ->and($settings->logo_path)->toBeNull();
});

it('grants settings.manage only to the admin role', function () {
    foreach (RoleName::cases() as $role) {
        $has = userWithRole($role)->checkPermissionTo(Permission::SettingsManage->value);

        expect($has)->toBe($role === RoleName::Admin);
    }
});

it('lets an admin update the clinic settings and audits the change', function () {
    $admin = userWithRole(RoleName::Admin);

    app(UpdateClinicSettingsAction::class)->execute($admin, clinicData());

    $settings = app(ClinicSettings::class);
    expect($settings->name)->toBe('Clínica Nueva')
        ->and($settings->primary_color)->toBe('#0ea5e9');

    $row = AuditLog::query()->where('action', 'settings.updated')->sole();
    expect($row->actor_id)->toBe($admin->id)
        ->and($row->before['name'])->toBe('Servicios Veterinarios San Francisco de Asís')
        ->and($row->after['name'])->toBe('Clínica Nueva');
});

it('denies updating the clinic settings without settings.manage', function (RoleName $role) {
    $actor = userWithRole($role);

    expect(fn () => app(UpdateClinicSettingsAction::class)->execute($actor, clinicData()))
        ->toThrow(AuthorizationException::class);

    expect(app(ClinicSettings::class)->name)->toBe('Servicios Veterinarios San Francisco de Asís')
        ->and(AuditLog::query()->where('action', 'settings.updated')->count())->toBe(0);
})->with([RoleName::Manager, RoleName::Reception, RoleName::Doctor, RoleName::Owner]);

it('rejects invalid colors and timezones without changing anything', function (array $override) {
    $admin = userWithRole(RoleName::Admin);

    expect(fn () => app(UpdateClinicSettingsAction::class)->execute($admin, clinicData($override)))
        ->toThrow(ValidationException::class);

    expect(app(ClinicSettings::class)->name)->toBe('Servicios Veterinarios San Francisco de Asís');
})->with([
    'not a hex color' => [['primaryColor' => 'red']],
    'css injection' => [['primaryColor' => '#fff;}body{display:none']],
    'unknown timezone' => [['timezone' => 'Mars/Olympus']],
    'empty name' => [['name' => '']],
]);

it('builds the panel branding from the settings', function () {
    app(UpdateClinicSettingsAction::class)->execute(userWithRole(RoleName::Admin), clinicData());

    $branding = app(ClinicBranding::class);

    expect($branding->name())->toBe('Clínica Nueva')
        ->and($branding->primaryColor())->toBe('#0ea5e9')
        ->and($branding->logoUrl())->toBeNull();
});

it('falls back to safe branding when the settings table does not exist yet', function () {
    DB::statement('DROP TABLE settings');

    $branding = app(ClinicBranding::class);

    expect($branding->name())->toBe(config('app.name'))
        ->and($branding->primaryColor())->toBe(ClinicBranding::DEFAULT_COLOR);
});

it('shows the clinic name on the panel login page', function () {
    app(UpdateClinicSettingsAction::class)->execute(userWithRole(RoleName::Admin), clinicData());

    $this->get('/vet/login')->assertOk()->assertSee('Clínica Nueva');
});

it('only lets users with settings.manage open the settings page', function () {
    $this->actingAs(userWithRole(RoleName::Admin))->get('/vet/clinic-settings')->assertOk();
    $this->actingAs(userWithRole(RoleName::Manager))->get('/vet/clinic-settings')->assertForbidden();
});

it('saves the clinic settings from the settings page', function () {
    $this->actingAs(userWithRole(RoleName::Admin));

    Livewire\Livewire::test(ManageClinicSettings::class)
        ->fillForm(['name' => 'Vet Prueba', 'primary_color' => '#112233', 'timezone' => 'America/Caracas'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(ClinicSettings::class)->name)->toBe('Vet Prueba')
        ->and(app(ClinicSettings::class)->primary_color)->toBe('#112233');
});
