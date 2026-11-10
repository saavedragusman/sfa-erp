<?php

declare(strict_types=1);

use App\Actions\Settings\CreateStaffMemberAction;
use App\Enums\RoleName;
use App\Exceptions\Domain\DuplicateStaffEmailException;
use App\Exceptions\Domain\InvalidStaffRolesException;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\SetStaffPasswordNotification;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

require_once __DIR__.'/StaffTestHelpers.php';

beforeEach(function () {
    seedRoles();
    Notification::fake();
    Filament::setCurrentPanel('vet');
});

it('BR-01 hides the staff screens from users without staff.manage', function (RoleName $role) {
    $this->actingAs(userWithRole($role));

    $this->get('/vet/staff')->assertForbidden();
    $this->get('/vet/staff/create')->assertForbidden();
})->with([RoleName::Reception, RoleName::Doctor]);

it('BR-01 shows the staff screens to users with staff.manage', function (RoleName $role) {
    $this->actingAs(userWithRole($role));

    $this->get('/vet/staff')->assertOk();
    $this->get('/vet/staff/create')->assertOk();
})->with([RoleName::Manager, RoleName::Admin]);

it('BR-01 denies the vet panel to owners and to users without a staff role', function () {
    $this->actingAs(userWithRole(RoleName::Owner))->get('/vet')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/vet')->assertForbidden();
});

it('BR-01 lets staff with any staff role reach the vet panel', function () {
    $this->actingAs(userWithRole(RoleName::Reception))->get('/vet')->assertOk();
});

it('BR-01 lists only staff and never owners', function () {
    $manager = userWithRole(RoleName::Manager);
    $doctor = userWithRole(RoleName::Doctor);
    $owner = userWithRole(RoleName::Owner);
    $noRole = User::factory()->create();

    Livewire::actingAs($manager)->test(ListStaff::class)
        ->assertCanSeeTableRecords([$manager, $doctor])
        ->assertCanNotSeeTableRecords([$owner, $noRole]);
});

it('BR-01 creates staff from the create screen through the action', function () {
    $manager = userWithRole(RoleName::Manager);

    Livewire::actingAs($manager)->test(CreateStaff::class)
        ->fillForm(['name' => 'Laura Gomez', 'email' => 'laura@example.com', 'roles' => ['doctor', 'reception']])
        ->call('create')
        ->assertHasNoFormErrors();

    $staff = User::where('email', 'laura@example.com')->firstOrFail();

    expect($staff->roles->pluck('name')->sort()->values()->all())->toBe(['doctor', 'reception']);
    Notification::assertSentTo($staff, SetStaffPasswordNotification::class);
    expect(AuditLog::where('action', 'staff.created')->where('actor_id', $manager->id)->exists())->toBeTrue();
});

it('BR-01 shows a form error when the email already exists', function () {
    User::factory()->create(['email' => 'dup@example.com']);

    Livewire::actingAs(userWithRole(RoleName::Manager))->test(CreateStaff::class)
        ->fillForm(['name' => 'Dup', 'email' => 'dup@example.com', 'roles' => ['doctor']])
        ->call('create')
        ->assertHasFormErrors(['email']);

    Notification::assertNothingSent();
});

it('BR-01 requires at least one role on the create screen', function () {
    Livewire::actingAs(userWithRole(RoleName::Manager))->test(CreateStaff::class)
        ->fillForm(['name' => 'No Roles', 'email' => 'noroles@example.com', 'roles' => []])
        ->call('create')
        ->assertHasFormErrors(['roles']);
});

it('BR-01 offers the admin role only to users with staff.manage_admins and never owner', function () {
    $options = fn (User $actor): array => Livewire::actingAs($actor)->test(CreateStaff::class)
        ->assertFormFieldExists('roles', fn (Select $field): bool => true)
        ->instance()->form->getComponent('roles')->getOptions();

    expect(array_keys($options(userWithRole(RoleName::Manager))))->toBe(['manager', 'reception', 'doctor'])
        ->and(array_keys($options(userWithRole(RoleName::Admin))))->toBe(['admin', 'manager', 'reception', 'doctor']);
});

it('BR-01 translates action exceptions into notifications and persists nothing', function (string $exception, string $message) {
    app()->bind(CreateStaffMemberAction::class, fn () => new class($exception)
    {
        public function __construct(private string $exception) {}

        public function execute(): never
        {
            throw new ($this->exception);
        }
    });

    Livewire::actingAs(userWithRole(RoleName::Manager))->test(CreateStaff::class)
        ->fillForm(['name' => 'Late', 'email' => 'late@example.com', 'roles' => ['doctor']])
        ->call('create')
        ->assertNotified($message);

    expect(User::where('email', 'late@example.com')->exists())->toBeFalse();
})->with([
    'forbidden' => [AuthorizationException::class, 'No tienes permiso para realizar esta acción.'],
    'invalid roles' => [InvalidStaffRolesException::class, 'Roles no válidos: se necesita al menos un rol de personal y el rol de tutor no se combina con ellos.'],
    'duplicate email' => [DuplicateStaffEmailException::class, 'Ya existe un usuario con ese correo.'],
]);

it('BR-01 edits staff roles from the edit screen through the action', function () {
    $manager = userWithRole(RoleName::Manager);
    $doctor = userWithRole(RoleName::Doctor);

    Livewire::actingAs($manager)->test(EditStaff::class, ['record' => $doctor->getRouteKey()])
        ->assertFormSet(['roles' => ['doctor']])
        ->fillForm(['name' => 'Renombrado', 'email' => $doctor->email, 'roles' => ['reception']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($doctor->fresh()->name)->toBe('Renombrado')
        ->and($doctor->fresh()->roles->pluck('name')->all())->toBe(['reception']);
    expect(AuditLog::where('action', 'staff.updated')->where('entity_id', $doctor->id)->exists())->toBeTrue();
});

it('BR-01 hides the edit screen of an existing admin from users without staff.manage_admins', function () {
    $admin = userWithRole(RoleName::Admin);

    $this->actingAs(userWithRole(RoleName::Manager))->get('/vet/staff/'.$admin->getRouteKey().'/edit')->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Admin))->get('/vet/staff/'.$admin->getRouteKey().'/edit')->assertOk();
});

it('BR-01 answers 404 for the edit screen of an owner', function () {
    $owner = userWithRole(RoleName::Owner);

    $this->actingAs(userWithRole(RoleName::Admin))->get('/vet/staff/'.$owner->getRouteKey().'/edit')->assertNotFound();
});

it('BR-01 does not allow deleting staff from the screen', function () {
    $admin = userWithRole(RoleName::Admin);

    expect(StaffResource::canDelete($admin))->toBeFalse()
        ->and(StaffResource::canDeleteAny())->toBeFalse();
});
