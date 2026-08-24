<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginAttemptSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'maxUserAttempts' => (int) $this->maxUserAttempts,
            'maxIpAttempts' => (int) $this->maxIpAttempts,
            'sessionTimeoutMinutes' => (int) $this->sessionTimeoutMinutes,
            'attemptWindowHours' => (int) config('security.attempt_window_hours'),
            'updatedBy' => $this->whenLoaded('updatedBy', fn () => [
                'uuid' => (string) $this->updatedBy->uuid,
                'fullName' => $this->updatedBy->fullName,
            ]),
            'createdAt' => $this->createdAt?->toIso8601String(),
            'updatedAt' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
