<?php

namespace App\Http\Resources;

use App\Models\BlockedIp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila del historial de bloqueos de IP (incluye las ya liberadas).
 * La dirección IP es el identificador natural del recurso; no hay ids internos.
 *
 * @mixin \App\Models\IpBlockedHistory
 */
class BlockedIpResource extends JsonResource
{
    public function __construct($resource, private readonly ?BlockedIp $current = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $isCurrentlyBlocked = (bool) ($this->current?->isBlockedPermanently ?? false);

        return [
            'uuid' => (string) $this->uuid,
            'ipAddress' => $this->ipAddress,
            'country' => $this->current?->country,
            'failedAttempts' => (int) $this->failedAttempts,
            'reason' => $this->reason,
            'blockedAt' => $this->createdAt?->toIso8601String(),
            'releasedAt' => $this->current?->releasedAt?->toIso8601String(),
            'isBlocked' => $isCurrentlyBlocked,
            'status' => $isCurrentlyBlocked ? 'blocked' : 'unblocked',
            'canUnblock' => $isCurrentlyBlocked,
        ];
    }
}
