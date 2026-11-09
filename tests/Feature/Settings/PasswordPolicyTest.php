<?php

declare(strict_types=1);

use App\Actions\Settings\CreateStaffMemberAction;
use App\DTOs\Settings\StaffMemberData;
use App\Enums\RoleName;
use App\Filament\Auth\ResetStaffPassword;
use App\Notifications\SetStaffPasswordNotification;
use App\Support\Auth\PasswordPolicy;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

require_once __DIR__.'/StaffTestHelpers.php';

beforeEach(function () {
    app()->setLocale('es');
    Filament::setCurrentPanel('vet');
});

it('BR-59 states the password minimum length as a hint and enforces the same number', function () {
    $min = PasswordPolicy::MIN_LENGTH;

    expect(PasswordPolicy::hint())->toBe("Mínimo {$min} caracteres.");

    $check = fn (string $value): bool => Validator::make(['password' => $value], ['password' => [Password::default()]])->passes();

    expect($check(str_repeat('a', $min - 1)))->toBeFalse()
        ->and($check(str_repeat('a', $min)))->toBeTrue();
});

it('BR-59 shows the password hint on the staff password reset page', function () {
    seedRoles();
    $user = userWithRole(RoleName::Doctor);
    $url = Filament::getPanel('vet')->getResetPasswordUrl('any-token', $user);

    $this->get($url)->assertOk()->assertSee('Mínimo 8 caracteres.');
});

it('BR-59 rejects a short password on the reset form with a Spanish message', function () {
    seedRoles();
    $user = userWithRole(RoleName::Doctor);
    $token = PasswordBroker::broker()->createToken($user);

    Livewire::test(ResetStaffPassword::class, ['email' => $user->email, 'token' => $token])
        ->fillForm(['password' => 'abc', 'passwordConfirmation' => 'abc'])
        ->call('resetPassword')
        ->assertHasFormErrors(['password'])
        ->assertSee('debe tener al menos 8 caracteres');

    expect(Hash::check('abc', $user->fresh()->password))->toBeFalse();
});

it('BR-59 lets new staff define their password from the emailed link', function () {
    seedRoles();
    Notification::fake();
    $manager = userWithRole(RoleName::Manager);
    $staff = app(CreateStaffMemberAction::class)->execute(
        $manager,
        new StaffMemberData(name: 'Nuevo', email: 'nuevo@example.com', roles: [RoleName::Doctor]),
    );

    $token = null;
    Notification::assertSentTo($staff, SetStaffPasswordNotification::class, function (SetStaffPasswordNotification $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    Livewire::test(ResetStaffPassword::class, ['email' => $staff->email, 'token' => $token])
        ->fillForm(['password' => 'a-good-password', 'passwordConfirmation' => 'a-good-password'])
        ->call('resetPassword')
        ->assertHasNoFormErrors();

    expect(Hash::check('a-good-password', $staff->fresh()->password))->toBeTrue();
});
