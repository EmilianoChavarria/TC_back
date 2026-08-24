<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Solicitud interceptada. El cuerpo ya viene sin credenciales ni ids internos.
 *
 * @mixin \App\Models\RequestLog
 */
class RequestLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'method' => $this->method,
            'path' => $this->path,
            'routeName' => $this->routeName,
            'statusCode' => $this->statusCode,
            'durationMs' => $this->durationMs,
            'actorName' => $this->actorName,
            'actorRole' => $this->actorRole,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'payload' => $this->payload,
            'occurredAt' => $this->createdAt?->toIso8601String(),
            'changes' => AuditLogResource::collection($this->whenLoaded('auditLogs')),
        ];
    }
}
