<?php

declare(strict_types=1);

namespace App\Support\Audit;

/**
 * Spanish label of an audit action. An action without a label is shown with its key, never hidden.
 */
final class AuditActionLabel
{
    private const LABELS = [
        'staff.created' => 'Personal creado',
        'staff.updated' => 'Personal editado',
        'settings.updated' => 'Ajustes de la clínica editados',
        'exchange_rate.created' => 'Tasa de cambio cargada',
        'exchange_rate.updated' => 'Tasa de cambio editada',
    ];

    public function for(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return self::LABELS;
    }
}
