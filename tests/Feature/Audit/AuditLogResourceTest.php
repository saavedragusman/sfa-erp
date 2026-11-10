<?php

declare(strict_types=1);

use App\Actions\Settings\UpdateClinicSettingsAction;
use App\DTOs\Settings\ClinicSettingsData;
use App\Enums\RoleName;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Livewire;

require_once dirname(__DIR__).'/Settings/StaffTestHelpers.php';

beforeEach(fn () => seedRoles());

function auditRow(array $overrides = []): AuditLog
{
    return AuditLog::create(array_merge([
        'actor_id' => null,
        'action' => 'staff.created',
        'entity_type' => 'user',
        'entity_id' => 1,
    ], $overrides));
}

it('BR-60 shows the audit screen only with audit.view', function () {
    $this->actingAs(userWithRole(RoleName::Admin))->get('/vet/audit-logs')->assertOk();
    $this->actingAs(userWithRole(RoleName::Manager))->get('/vet/audit-logs')->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Reception))->get('/vet/audit-logs')->assertForbidden();
});

it('BR-60 denies the detail page without audit.view', function () {
    $log = auditRow();

    $this->actingAs(userWithRole(RoleName::Manager))->get("/vet/audit-logs/{$log->id}")->assertForbidden();
});

it('BR-60 exposes no create, edit or delete route', function () {
    $log = auditRow();
    $this->actingAs(userWithRole(RoleName::Admin));

    $this->get('/vet/audit-logs/create')->assertNotFound();
    $this->get("/vet/audit-logs/{$log->id}/edit")->assertNotFound();
    $this->delete("/vet/audit-logs/{$log->id}")->assertStatus(405);
});

it('BR-60 offers no row or bulk actions on the list', function () {
    $this->actingAs(userWithRole(RoleName::Admin));
    auditRow();

    Livewire::test(ListAuditLogs::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('BR-60 lists logs with the actor and falls back to Sistema', function () {
    $actor = User::factory()->create(['name' => 'Ana Admin']);
    $this->actingAs(userWithRole(RoleName::Admin));
    $withActor = auditRow(['actor_id' => $actor->id]);
    $system = auditRow(['action' => 'settings.updated']);

    Livewire::test(ListAuditLogs::class)
        ->assertCanSeeTableRecords([$withActor, $system])
        ->assertSee('Ana Admin')
        ->assertSee('Sistema')
        ->assertSee('Personal creado');
});

it('BR-60 filters by actor, action and date range', function () {
    $actor = User::factory()->create();
    $this->actingAs(userWithRole(RoleName::Admin));
    $byActor = auditRow(['actor_id' => $actor->id, 'action' => 'staff.updated', 'created_at' => '2026-11-01 12:00:00']);
    $other = auditRow(['action' => 'settings.updated', 'created_at' => '2026-11-09 12:00:00']);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('actor_id', $actor->id)
        ->assertCanSeeTableRecords([$byActor])
        ->assertCanNotSeeTableRecords([$other])
        ->resetTableFilters()
        ->filterTable('action', 'settings.updated')
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$byActor])
        ->resetTableFilters()
        ->filterTable('created_at', ['created_from' => '2026-11-05', 'created_until' => '2026-11-10'])
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$byActor]);
});

it('BR-60 shows before and after values on the detail page', function () {
    $admin = userWithRole(RoleName::Admin);
    $log = auditRow([
        'action' => 'exchange_rate.updated',
        'before' => ['rate' => '36.50000000'],
        'after' => ['rate' => '37.25000000'],
    ]);

    $this->actingAs($admin)->get("/vet/audit-logs/{$log->id}")
        ->assertOk()
        ->assertSee('36.50000000')
        ->assertSee('37.25000000')
        ->assertSee('Tasa de cambio editada');
});

it('BR-60 shows times in the clinic timezone', function () {
    $admin = userWithRole(RoleName::Admin);
    app(UpdateClinicSettingsAction::class)->execute($admin, new ClinicSettingsData('Vet', null, null, null, '#f59e0b', 'America/Caracas', null));
    $log = auditRow(['created_at' => '2026-11-09 03:00:00']);
    $this->actingAs($admin);

    // 03:00 UTC is 23:00 of the previous day in Caracas (UTC-4).
    Livewire::test(ListAuditLogs::class)->assertSee('08/11/2026 23:00');
});

it('BR-60 keeps the clinic settings screen working', function () {
    $this->actingAs(userWithRole(RoleName::Admin))->get('/vet/clinic-settings')->assertOk();
});
