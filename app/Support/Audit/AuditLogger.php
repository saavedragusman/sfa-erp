<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes one audit row using the caller's open transaction (BR-57).
 * Never stores passwords, tokens or secrets (BR-56).
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(?User $actor, string $action, Model $entity, ?array $before, ?array $after): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'ip' => request()->ip(),
            'before' => $before === null ? null : $this->redact($before),
            'after' => $after === null ? null : $this->redact($after),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function redact(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match('/password|token|secret/i', $key) === 1) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $clean;
    }
}
