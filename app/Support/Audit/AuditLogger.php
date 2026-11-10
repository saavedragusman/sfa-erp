<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes one audit row using the caller's open transaction (BR-57).
 * Never stores passwords, tokens or secrets (BR-56): an explicit key list plus the model's hidden attributes.
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
            'before' => $before === null ? null : $this->redact($before, $entity),
            'after' => $after === null ? null : $this->redact($after, $entity),
        ]);
    }

    /**
     * Keys that are never stored, compared case-insensitively and by exact name.
     * `*_hash` is the only suffix rule; the audited model's `$hidden` attributes are added on top.
     */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'secret',
        'api_key',
        'hash',
    ];

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function redact(array $values, Model $entity): array
    {
        $hidden = array_map('strtolower', $entity->getHidden());
        $clean = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isRedacted(strtolower($key), $hidden)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->redact($value, $entity) : $value;
        }

        return $clean;
    }

    /**
     * @param  array<array-key, string>  $hidden
     */
    private function isRedacted(string $key, array $hidden): bool
    {
        return in_array($key, self::REDACTED_KEYS, true)
            || str_ends_with($key, '_hash')
            || in_array($key, $hidden, true);
    }
}
