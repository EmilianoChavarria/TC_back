<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Estado de auditoría de la solicitud en curso (singleton por petición).
 *
 * Durante una solicitud HTTP las filas se acumulan en memoria y se escriben al
 * final, cuando ya existe el `requestlogs` al que enlazarlas. Fuera de una
 * solicitud (consola, tareas programadas) se escriben de inmediato.
 */
class AuditContext
{
    private bool $collecting = false;
    private bool $enabled = true;

    /** @var array<int, array<string, mixed>> */
    private array $buffer = [];

    private ?int $userId = null;
    private ?string $actorName = null;
    private ?string $actorRole = null;
    private ?string $ipAddress = null;

    public function startCollecting(?string $ipAddress = null): void
    {
        $this->collecting = true;
        $this->buffer = [];
        $this->ipAddress = $ipAddress;
    }

    public function isEnabled(): bool
    {
        return $this->enabled && (bool) config('audit.enabled', true);
    }

    /** Identifica al autor de los cambios de esta solicitud. */
    public function identify(?User $user, ?string $ipAddress = null): void
    {
        $this->userId = $user?->id !== null ? (int) $user->id : null;
        $this->actorName = $user?->fullName;
        $this->actorRole = $user?->roleName() ?: null;

        if ($ipAddress !== null) {
            $this->ipAddress = $ipAddress;
        }
    }

    /** @return array{userId: ?int, actorName: string, actorRole: ?string, ipAddress: ?string} */
    public function actor(): array
    {
        return [
            'userId' => $this->userId,
            'actorName' => $this->actorName ?: (string) config('audit.system_actor_label'),
            'actorRole' => $this->actorRole,
            'ipAddress' => $this->ipAddress,
        ];
    }

    /**
     * Ejecuta una operación sin registrar auditoría. Útil en seeders,
     * migraciones de datos y en la propia escritura de los registros.
     */
    public function withoutAuditing(callable $callback): mixed
    {
        $previous = $this->enabled;
        $this->enabled = false;

        try {
            return $callback();
        } finally {
            $this->enabled = $previous;
        }
    }

    /** @param array<string, mixed> $row */
    public function record(array $row): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if ($this->collecting) {
            $this->buffer[] = $row;

            return;
        }

        $this->persist([$row], null);
    }

    /** Escribe lo acumulado y lo enlaza con la solicitud que lo provocó. */
    public function flush(?RequestLog $requestLog): void
    {
        $rows = $this->buffer;
        $this->buffer = [];
        $this->collecting = false;

        if ($rows === []) {
            return;
        }

        $this->persist($rows, $requestLog);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function persist(array $rows, ?RequestLog $requestLog): void
    {
        $actor = $this->actor();
        $now = Carbon::now();

        foreach ($rows as $row) {
            try {
                AuditLog::create($row + $actor + [
                    'requestLogId' => $requestLog?->id,
                    'createdAt' => $now,
                ]);
            } catch (Throwable $e) {
                // La auditoría nunca debe tumbar la operación que la originó.
                Log::error('[Audit] no se pudo registrar el cambio', [
                    'event' => $row['event'] ?? null,
                    'table' => $row['auditableTable'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
