<?php

declare(strict_types=1);

use App\Enums\RoleName;

it('BR-01 treats every role except owner as staff', function () {
    expect(RoleName::staff())->toBe([RoleName::Admin, RoleName::Manager, RoleName::Reception, RoleName::Doctor])
        ->and(RoleName::Owner->isStaff())->toBeFalse()
        ->and(RoleName::staffValues())->toBe(['admin', 'manager', 'reception', 'doctor']);
});
