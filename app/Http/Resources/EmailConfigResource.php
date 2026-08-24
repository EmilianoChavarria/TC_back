<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'emailSupport' => $this->emailSupport,
            'emailMode' => $this->emailMode,
            'overrideEmail' => $this->overrideEmail,
            'mailer' => (string) config('mail.default'),
            'fromAddress' => (string) config('mail.from.address'),
            'fromName' => (string) config('mail.from.name'),
            'createdAt' => $this->createdAt?->toIso8601String(),
            'updatedAt' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
