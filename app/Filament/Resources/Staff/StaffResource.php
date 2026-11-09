<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff;

use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Staff\Schemas\StaffForm;
use App\Filament\Resources\Staff\Tables\StaffTable;
use App\Models\User;
use App\Queries\Settings\StaffMembersQuery;
use App\Support\Staff\StaffRoles;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Screen over CreateStaffMemberAction / UpdateStaffMemberAction. The Actions stay the
 * authority; the abilities here only hide what the actor cannot do (BR-01).
 */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static ?string $modelLabel = 'miembro del personal';

    protected static ?string $pluralModelLabel = 'Personal';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    /**
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        return app(StaffMembersQuery::class)->builder();
    }

    // Filament allows any ability the policy does not define, so every response is explicit.
    public static function getViewAnyAuthorizationResponse(): Response
    {
        return Gate::inspect('viewAnyStaff', User::class);
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Gate::inspect('createStaff', User::class);
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        $response = Gate::inspect('updateStaff', $record);

        if ($response->denied()) {
            return $response;
        }

        // Editing an existing admin needs staff.manage_admins (DEC-11).
        if ($record instanceof User && app(StaffRoles::class)->holdsAdmin($record)) {
            return Gate::inspect('assignAdminRole', User::class);
        }

        return $response;
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function form(Schema $schema): Schema
    {
        return StaffForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}
