<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\NotificationRecipient
 */
class NotificationRecipientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $deleted = $this->isDeleted();

        return [
            'uuid' => (string) $this->uuid,
            'email' => $this->email,
            'name' => $this->name,
            'isActive' => (bool) $this->isActive,
            'isDeleted' => $deleted,

            // Tres estados y no dos: eliminado, silenciado y activo son cosas
            // distintas. Un destinatario "inactivo" sigue en la lista y puede
            // volver a encenderse; uno eliminado ya no está.
            'status' => $deleted ? 'deleted' : ((bool) $this->isActive ? 'active' : 'paused'),
            'statusLabel' => $deleted ? 'Eliminado' : ((bool) $this->isActive ? 'Activo' : 'Pausado'),

            'createdAt' => $this->createdAt?->toIso8601String(),
            'updatedAt' => $this->updatedAt?->toIso8601String(),
            'deletedAt' => $this->deletedAt?->toIso8601String(),
        ];
    }
}
