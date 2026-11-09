<?php

declare(strict_types=1);

namespace App\Support\Branding;

use App\Settings\ClinicSettings;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Panel branding read from ClinicSettings. Falls back to safe defaults when the settings
 * cannot be read (for example while migrations are still running), so a broken settings
 * table never takes the login page down.
 */
final class ClinicBranding
{
    public const DEFAULT_COLOR = '#f59e0b';

    public function name(): string
    {
        return $this->read(fn (ClinicSettings $s): string => $s->name, (string) config('app.name'));
    }

    public function primaryColor(): string
    {
        return $this->read(fn (ClinicSettings $s): string => $s->primary_color, self::DEFAULT_COLOR);
    }

    public function logoUrl(): ?string
    {
        $path = $this->read(fn (ClinicSettings $s): ?string => $s->logo_path, null);

        return $path === null ? null : Storage::disk('public')->url($path);
    }

    /**
     * @template T
     *
     * @param  callable(ClinicSettings): T  $get
     * @param  T  $fallback
     * @return T
     */
    private function read(callable $get, mixed $fallback): mixed
    {
        try {
            return $get(app(ClinicSettings::class));
        } catch (Throwable) {
            return $fallback;
        }
    }
}
