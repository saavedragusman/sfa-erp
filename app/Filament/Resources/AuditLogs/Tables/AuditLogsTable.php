<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use App\Models\User;
use App\Settings\ClinicSettings;
use App\Support\Audit\AuditActionLabel;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public const DATE_FORMAT = 'd/m/Y H:i';

    public static function configure(Table $table): Table
    {
        $labels = new AuditActionLabel;

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime(self::DATE_FORMAT)
                    ->timezone(fn (): string => app(ClinicSettings::class)->timezone)
                    ->sortable(),
                TextColumn::make('actor.name')->label('Actor')->placeholder('Sistema')->default('Sistema'),
                TextColumn::make('action')
                    ->label('Acción')
                    ->formatStateUsing(fn (string $state): string => $labels->for($state))
                    ->searchable(),
                TextColumn::make('entity_type')->label('Entidad')->formatStateUsing(fn (AuditLog $record): string => $record->entity_type.' #'.$record->entity_id),
                TextColumn::make('ip')->label('IP')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('actor_id')
                    ->label('Actor')
                    ->options(fn (): array => User::query()
                        ->whereIn('id', AuditLog::query()->whereNotNull('actor_id')->select('actor_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                SelectFilter::make('action')
                    ->label('Acción')
                    ->options(fn (): array => AuditLog::query()->distinct()->orderBy('action')->pluck('action')
                        ->mapWithKeys(fn (string $action): array => [$action => $labels->for($action)])
                        ->all()),
                SelectFilter::make('entity_type')
                    ->label('Entidad')
                    ->options(fn (): array => AuditLog::query()->distinct()->orderBy('entity_type')->pluck('entity_type', 'entity_type')->all()),
                Filter::make('created_at')
                    ->label('Fecha')
                    ->schema([
                        DatePicker::make('created_from')->label('Desde'),
                        DatePicker::make('created_until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['created_from'] ?? null, fn (Builder $q, string $from): Builder => $q->where('created_at', '>=', self::startOfDay($from)))
                        ->when($data['created_until'] ?? null, fn (Builder $q, string $until): Builder => $q->where('created_at', '<', self::startOfDay($until)->addDay()))),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }

    /**
     * Day boundaries are the clinic's, not UTC's: the screen shows clinic time (Constitution section 6).
     */
    private static function startOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, app(ClinicSettings::class)->timezone)->startOfDay()->utc();
    }
}
