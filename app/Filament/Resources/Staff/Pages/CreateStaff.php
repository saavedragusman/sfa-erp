<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Pages;

use App\Actions\Settings\CreateStaffMemberAction;
use App\DTOs\Settings\StaffMemberData;
use App\Filament\Resources\Staff\Pages\Concerns\HandlesStaffActionErrors;
use App\Filament\Resources\Staff\StaffResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaff extends CreateRecord
{
    use HandlesStaffActionErrors;

    protected static string $resource = StaffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->runStaffAction(
            fn (): Model => app(CreateStaffMemberAction::class)->execute($this->actor(), StaffMemberData::fromForm($data)),
        );
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Personal creado. Se envió el enlace para definir su contraseña.';
    }
}
