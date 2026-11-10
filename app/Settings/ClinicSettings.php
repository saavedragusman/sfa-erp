<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Everything that varies per clinic and is not infrastructure (Constitution section 1).
 */
final class ClinicSettings extends Settings
{
    public string $name;

    public ?string $rif;

    public ?string $address;

    public ?string $phone;

    /** Path on the public disk, or null to show the clinic name instead of a logo. */
    public ?string $logo_path;

    /** Hex color (#rrggbb) used as the panel's primary color. */
    public string $primary_color;

    /** IANA timezone in which stored UTC times are displayed. */
    public string $timezone;

    public static function group(): string
    {
        return 'clinic';
    }
}
