<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Holiday
 */
class HolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $deleted = $this->isDeleted();

        return [
            'uuid' => (string) $this->uuid,
            'holidayDate' => $this->holidayDate?->toDateString(),
            'year' => $this->year(),
            'description' => $this->description,
            'isDeleted' => $deleted,
            'status' => $deleted ? 'deleted' : 'active',
            'statusLabel' => $deleted ? 'Eliminado' : 'Vigente',
            'createdAt' => $this->createdAt?->toIso8601String(),
            'updatedAt' => $this->updatedAt?->toIso8601String(),
            'deletedAt' => $this->deletedAt?->toIso8601String(),
        ];
    }
}
