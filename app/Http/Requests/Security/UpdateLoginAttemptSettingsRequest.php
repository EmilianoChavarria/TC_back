<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoginAttemptSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maxUserAttempts' => ['required', 'integer', 'min:1', 'max:1000'],
            'maxIpAttempts' => ['required', 'integer', 'min:1', 'max:1000'],
            'sessionTimeoutMinutes' => ['required', 'integer', 'min:1', 'max:10080'],
        ];
    }
}
