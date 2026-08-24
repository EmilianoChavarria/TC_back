<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila del historial de bloqueos de usuario (incluye los ya liberados).
 * Sólo identificadores públicos: `uuid` de la fila y `userUuid` del usuario.
 *
 * @mixin \App\Models\UserBlockedHistory
 */
class BlockedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->whenLoaded('user');
        $isCurrentlyBlocked = (bool) ($user?->security?->isBlocked ?? false);

        return [
            'uuid' => (string) $this->uuid,
            'userUuid' => $user?->uuid,
            'fullName' => $user?->fullName,
            'email' => $user?->email,
            'roleName' => $user?->roleName(),
            'failedAttempts' => (int) $this->failedAttempts,
            'reason' => $this->reason,
            'ipAddress' => $this->ipAddress,
            'blockedAt' => $this->createdAt?->toIso8601String(),
            'isBlocked' => $isCurrentlyBlocked,
            'status' => $isCurrentlyBlocked ? 'blocked' : 'unblocked',
            'canUnblock' => $isCurrentlyBlocked,
        ];
    }
}
