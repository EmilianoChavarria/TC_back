<?php

namespace App\Http\Resources;

use App\Support\Decimals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExchangeRateFactor
 */
class ExchangeRateFactorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $deleted = $this->isDeleted();

        return [
            'uuid' => (string) $this->uuid,
            'code' => (int) $this->code,
            'rangeFrom' => Decimals::rate($this->rangeFrom),
            'rangeTo' => Decimals::rate($this->rangeTo),
            'factor' => Decimals::factor($this->factor),
            'updatedBy' => $this->updatedBy?->fullName,
            'isDeleted' => $deleted,
            'status' => $deleted ? 'deleted' : 'active',
            'statusLabel' => $deleted ? 'Eliminado' : 'Vigente',
            'createdAt' => $this->createdAt?->toIso8601String(),
            'updatedAt' => $this->updatedAt?->toIso8601String(),
            'deletedAt' => $this->deletedAt?->toIso8601String(),
        ];
    }
}
