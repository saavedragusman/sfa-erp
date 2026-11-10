<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Tables;

use App\Enums\RoleName;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('email')->label('Correo')->searchable()->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RoleName::from($state)->label()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
