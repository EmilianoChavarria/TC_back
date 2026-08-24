<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequirementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'minLength' => ['required', 'integer', 'min:6', 'max:128'],
            // 0 = la contraseña no caduca
            'expirationDays' => ['required', 'integer', 'min:0', 'max:3650'],
            'requireUppercase' => ['required', 'boolean'],
            'requireLowercase' => ['required', 'boolean'],
            'requireNumbers' => ['required', 'boolean'],
            'requireSpecialChars' => ['required', 'boolean'],
            'allowedSpecialChars' => ['nullable', 'string', 'max:255'],
        ];
    }
}
