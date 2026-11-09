<?php

declare(strict_types=1);

use App\Actions\Settings\CreateStaffMemberAction;
use App\Actions\Settings\UpdateStaffMemberAction;
use App\DTOs\Settings\StaffMemberData;
use App\Enums\RoleName;
use App\Notifications\SetStaffPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

require_once __DIR__.'/StaffTestHelpers.php';

beforeEach(function () {
    seedRoles();
    Notification::fake();
});

function staffData(): StaffMemberData
{
    return new StaffMemberData(name: 'Nuevo Personal', email: 'nuevo@example.com', roles: [RoleName::Doctor]);
}

it('BR-59 sends set-password link to new staff only after commit', function () {
    $manager = userWithRole(RoleName::Manager);
    $created = null;

    // Everything inside an outer transaction that rolls back must never notify.
    try {
        DB::transaction(function () use ($manager, &$created) {
            $created = app(CreateStaffMemberAction::class)->execute($manager, staffData());
            Notification::assertNothingSent();

            throw new RuntimeException('outer rollback');
        });
    } catch (RuntimeException) {
    }

    Notification::assertNothingSent();

    $staff = app(CreateStaffMemberAction::class)->execute($manager, staffData());

    Notification::assertSentToTimes($staff, SetStaffPasswordNotification::class, 1);
});

it('BR-59 link carries a valid password broker token for the new staff member', function () {
    $manager = userWithRole(RoleName::Manager);

    $staff = app(CreateStaffMemberAction::class)->execute($manager, staffData());

    Notification::assertSentTo($staff, SetStaffPasswordNotification::class, function (SetStaffPasswordNotification $notification) use ($staff) {
        return Password::broker()->tokenExists($staff, $notification->token);
    });
});

it('BR-59 link points to the staff panel password reset page', function () {
    $manager = userWithRole(RoleName::Manager);

    $staff = app(CreateStaffMemberAction::class)->execute($manager, staffData());

    Notification::assertSentTo($staff, SetStaffPasswordNotification::class, function (SetStaffPasswordNotification $notification) use ($staff) {
        $url = $notification->toMail($staff)->actionUrl;

        return str_contains($url, '/vet/password-reset/reset')
            && str_contains($url, 'token='.$notification->token)
            && str_contains($url, urlencode('nuevo@example.com'));
    });
});

it('BR-59 does not send the link when updating staff', function () {
    $manager = userWithRole(RoleName::Manager);
    $member = userWithRole(RoleName::Doctor);

    app(UpdateStaffMemberAction::class)->execute($manager, $member, staffData());

    Notification::assertNothingSent();
});

it('BR-59 keeps the panel login reachable after enabling password reset', function () {
    $this->get('/vet/login')->assertOk();
    $this->get('/vet/password-reset/request')->assertOk();
});
