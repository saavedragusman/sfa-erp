<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\DTOs\Settings\ClinicSettingsData;
use App\Models\User;
use App\Settings\ClinicSettings;
use App\Support\Audit\AuditLogger;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class UpdateClinicSettingsAction
{
    public function __construct(
        private readonly ClinicSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, ClinicSettingsData $data): ClinicSettings
    {
        Gate::forUser($actor)->authorize('manage', ClinicSettings::class);

        Validator::make([
            'name' => $data->name,
            'primary_color' => $data->primaryColor,
            'timezone' => $data->timezone,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
        ])->validate();

        return DB::transaction(function () use ($actor, $data): ClinicSettings {
            $before = $this->snapshot();

            $this->settings->name = $data->name;
            $this->settings->rif = $data->rif;
            $this->settings->address = $data->address;
            $this->settings->phone = $data->phone;
            $this->settings->logo_path = $data->logoPath;
            $this->settings->primary_color = $data->primaryColor;
            $this->settings->timezone = $data->timezone;
            $this->settings->save();

            $this->audit->recordSingleton($actor, 'settings.updated', 'clinic_settings', $before, $this->snapshot());

            return $this->settings;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return $this->settings->toArray();
    }
}
