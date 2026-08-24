<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'fullName' => $this->fullName,
            'email' => $this->email,
            'roleName' => $this->roleName(),
            'preferredLanguage' => $this->preferredLanguage,
            'isActive' => (bool) $this->isActive,
            'mustChangePassword' => (bool) $this->mustChangePassword,
            'passwordChangedAt' => $this->passwordChangedAt?->toIso8601String(),
            'createdAt' => $this->createdAt?->toIso8601String(),
        ];
    }
}
