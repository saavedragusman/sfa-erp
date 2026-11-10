<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Pages;

use App\Actions\Settings\UpdateStaffMemberAction;
use App\DTOs\Settings\StaffMemberData;
use App\Filament\Resources\Staff\Pages\Concerns\HandlesStaffActionErrors;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use App\Support\Staff\StaffRoles;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStaff extends EditRecord
{
    use HandlesStaffActionErrors;

    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $member */
        $member = $this->getRecord();
        $data['roles'] = app(StaffRoles::class)->namesOf($member);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->runStaffAction(function () use ($record, $data): Model {
            /** @var User $record */
            return app(UpdateStaffMemberAction::class)->execute($this->actor(), $record, StaffMemberData::fromForm($data));
        });
    }
}
