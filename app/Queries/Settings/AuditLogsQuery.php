<?php

declare(strict_types=1);

namespace App\Queries\Settings;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read model of the audit screen: newest first, with the actor loaded in one extra query.
 */
final class AuditLogsQuery
{
    /**
     * @return Builder<AuditLog>
     */
    public function builder(): Builder
    {
        return AuditLog::query()->with('actor')->orderByDesc('id');
    }
}
