<?php

declare(strict_types=1);

namespace App\DTOs\Settings;

final readonly class ClinicSettingsData
{
    public function __construct(
        public string $name,
        public ?string $rif,
        public ?string $address,
        public ?string $phone,
        public string $primaryColor,
        public string $timezone,
        public ?string $logoPath,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromForm(array $state): self
    {
        $text = fn (string $key): ?string => ($state[$key] ?? null) === null || trim((string) $state[$key]) === '' ? null : trim((string) $state[$key]);

        return new self(
            name: trim((string) ($state['name'] ?? '')),
            rif: $text('rif'),
            address: $text('address'),
            phone: $text('phone'),
            primaryColor: (string) ($state['primary_color'] ?? ''),
            timezone: (string) ($state['timezone'] ?? ''),
            logoPath: $text('logo_path'),
        );
    }
}
