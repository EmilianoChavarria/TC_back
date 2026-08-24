<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PasswordRequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'minLength' => (int) $this->minLength,
            'requireUppercase' => (bool) $this->requireUppercase,
            'requireLowercase' => (bool) $this->requireLowercase,
            'requireNumbers' => (bool) $this->requireNumbers,
            'requireSpecialChars' => (bool) $this->requireSpecialChars,
            'allowedSpecialChars' => (string) $this->allowedSpecialChars,
            'expirationDays' => (int) $this->expirationDays,
            'updatedBy' => $this->whenLoaded('updatedBy', fn () => [
                'uuid' => (string) $this->updatedBy->uuid,
                'fullName' => $this->updatedBy->fullName,
            ]),
            'createdAt' => $this->createdAt?->toIso8601String(),
            'updatedAt' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
