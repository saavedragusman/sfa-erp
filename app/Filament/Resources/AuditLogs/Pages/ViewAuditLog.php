<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;

    /**
     * The record key is a bigint: a non-numeric segment such as /create is a 404, not a database error.
     */
    protected function resolveRecord(int|string $key): AuditLog
    {
        if (! ctype_digit((string) $key)) {
            throw (new ModelNotFoundException)->setModel(AuditLog::class, [$key]);
        }

        /** @var AuditLog */
        return parent::resolveRecord($key);
    }
}
