<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $deleted = $this->deletedAt !== null;
        $blocked = (bool) ($this->security?->isBlocked ?? false);

        return [
            'uuid' => (string) $this->uuid,
            'fullName' => $this->fullName,
            'email' => $this->email,
            'roleName' => $this->roleName(),
            'isActive' => (bool) $this->isActive,
            'isDeleted' => $deleted,
            'isBlocked' => $blocked,
            'status' => $this->status($deleted, $blocked),
            'statusLabel' => $this->statusLabel($deleted, $blocked),
            'mustChangePassword' => (bool) $this->mustChangePassword,
            'passwordChangedAt' => $this->passwordChangedAt?->toIso8601String(),
            'lastLoginAt' => $this->security?->lastLoginAt?->toIso8601String(),
            'createdAt' => $this->createdAt?->toIso8601String(),
        ];
    }

    private function status(bool $deleted, bool $blocked): string
    {
        if ($deleted || !$this->isActive) {
            return 'inactive';
        }

        return $blocked ? 'blocked' : 'active';
    }

    private function statusLabel(bool $deleted, bool $blocked): string
    {
        return match ($this->status($deleted, $blocked)) {
            'inactive' => 'Inactivo',
            'blocked' => 'Bloqueado',
            default => 'Activo',
        };
    }
}
