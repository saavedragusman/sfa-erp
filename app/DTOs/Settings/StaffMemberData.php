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

    /**
     * @param  array<string, mixed>  $data  Validated staff form state.
     */
    public static function fromForm(array $data): self
    {
        /** @var list<string> $roles */
        $roles = $data['roles'] ?? [];

        return new self(
            name: (string) $data['name'],
            email: (string) $data['email'],
            roles: array_map(fn (string $role): RoleName => RoleName::from($role), $roles),
        );
    }
}
