<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Settings\UpdateClinicSettingsAction;
use App\DTOs\Settings\ClinicSettingsData;
use App\Models\User;
use App\Settings\ClinicSettings;
use BackedEnum;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @property-read Schema $form
 *
 * Screen over UpdateClinicSettingsAction. The Action stays the authority (settings.manage).
 */
class ManageClinicSettings extends Page
{
    protected static ?string $slug = 'clinic-settings';

    protected static ?string $title = 'Ajustes de la clínica';

    protected static ?string $navigationLabel = 'Ajustes';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.manage-clinic-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && Gate::forUser($user)->allows('manage', ClinicSettings::class);
    }

    public function mount(): void
    {
        $this->form->fill(app(ClinicSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        $zones = DateTimeZone::listIdentifiers();

        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('name')->label('Nombre de la clínica')->required()->maxLength(255),
                TextInput::make('rif')->label('RIF')->maxLength(30),
                TextInput::make('address')->label('Dirección')->maxLength(255),
                TextInput::make('phone')->label('Teléfono')->tel()->maxLength(30),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->disk('public')
                    ->directory('branding')
                    ->maxSize(2048),
                ColorPicker::make('primary_color')->label('Color principal')->required()->regex('/^#[0-9a-fA-F]{6}$/'),
                Select::make('timezone')
                    ->label('Zona horaria')
                    ->options(array_combine($zones, $zones))
                    ->searchable()
                    ->required(),
            ]);
    }

    public function save(): void
    {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            app(UpdateClinicSettingsAction::class)->execute($actor, ClinicSettingsData::fromForm($this->form->getState()));
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('No se guardó')->body(collect($exception->errors())->flatten()->implode(' '))->send();

            return;
        }

        Notification::make()->success()->title('Ajustes guardados')->send();

        $this->redirect(static::getUrl());
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Guardar')->submit('save')];
    }
}
