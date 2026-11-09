<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use App\Providers\Filament\VetPanelProvider;
use App\Queries\Settings\StaffMembersQuery;
use Filament\Panel;

require_once __DIR__.'/StaffTestHelpers.php';

beforeEach(function () {
    seedRoles();
});

it('BR-01 keeps panel access and the staff list in agreement for every role', function (RoleName $role) {
    $user = userWithRole($role);

    $listed = app(StaffMembersQuery::class)->builder()->whereKey($user->getKey())->exists();
    $canAccess = $user->canAccessPanel(Panel::make()->id(VetPanelProvider::ID));

    expect($canAccess)->toBe($role->isStaff())
        ->and($listed)->toBe($canAccess);
})->with(RoleName::cases());

it('BR-01 denies a user without roles in both the panel and the staff list', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Panel::make()->id(VetPanelProvider::ID)))->toBeFalse()
        ->and(app(StaffMembersQuery::class)->builder()->whereKey($user->getKey())->exists())->toBeFalse();
});

it('BR-01 grants the staff rule only on the vet panel', function (RoleName $role) {
    $user = userWithRole($role);

    expect($user->canAccessPanel(Panel::make()->id('portal')))->toBeFalse();
})->with(RoleName::cases());
