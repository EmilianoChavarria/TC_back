<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Entrada de la línea de tiempo: qué pasó, sobre qué registro, quién y cuándo.
 * Sin llaves primarias: el registro afectado se identifica por su uuid público.
 *
 * @mixin \App\Models\AuditLog
 */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'event' => $this->event,
            'eventLabel' => $this->eventLabel(),
            'table' => $this->auditableTable,
            'recordUuid' => $this->auditableUuid,
            'recordLabel' => $this->recordLabel,
            'changedColumns' => $this->changedColumns,
            'oldValues' => $this->oldValues,
            'newValues' => $this->newValues,
            'recordCreatedAt' => $this->recordCreatedAt?->toIso8601String(),
            'recordUpdatedAt' => $this->recordUpdatedAt?->toIso8601String(),
            'actorName' => $this->actorName,
            'actorRole' => $this->actorRole,
            'ipAddress' => $this->ipAddress,
            'occurredAt' => $this->createdAt?->toIso8601String(),
            'request' => $this->whenLoaded('requestLog', fn () => [
                'uuid' => (string) $this->requestLog->uuid,
                'method' => $this->requestLog->method,
                'path' => $this->requestLog->path,
                'statusCode' => $this->requestLog->statusCode,
            ]),
        ];
    }
}
