<?php

declare(strict_types=1);

namespace App\DTOs\Settings;

use App\Enums\RoleName;

final readonly class StaffMemberData
{
    /**
     * @param  list<RoleName>  $roles
     */
    public function __construct(
        public string $name,
        public string $email,
        public array $roles,
    ) {}
}
