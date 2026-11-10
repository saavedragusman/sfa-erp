<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Schemas;

use App\Models\AuditLog;
use App\Settings\ClinicSettings;
use App\Support\Audit\AuditActionLabel;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $labels = new AuditActionLabel;
        $json = fn (?array $values): string => $values === null
            ? '—'
            : (string) json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $schema->components([
            TextEntry::make('created_at')
                ->label('Fecha')
                ->dateTime('d/m/Y H:i:s')
                ->timezone(fn (): string => app(ClinicSettings::class)->timezone),
            TextEntry::make('actor.name')->label('Actor')->placeholder('Sistema')->default('Sistema'),
            TextEntry::make('action')->label('Acción')->formatStateUsing(fn (string $state): string => $labels->for($state)),
            TextEntry::make('entity_type')->label('Entidad')->formatStateUsing(fn (AuditLog $record): string => $record->entity_type.' #'.$record->entity_id),
            TextEntry::make('ip')->label('IP')->placeholder('—'),
            TextEntry::make('before')->label('Antes')->state(fn (AuditLog $record): string => $json($record->before))->extraAttributes(['class' => 'font-mono whitespace-pre-wrap']),
            TextEntry::make('after')->label('Después')->state(fn (AuditLog $record): string => $json($record->after))->extraAttributes(['class' => 'font-mono whitespace-pre-wrap']),
        ]);
    }
}
