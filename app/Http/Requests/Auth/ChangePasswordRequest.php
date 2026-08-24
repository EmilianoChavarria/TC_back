<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'max:255'],
            'newPassword' => ['required', 'string', 'max:255', 'different:currentPassword', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'newPassword.different' => 'La nueva contraseña debe ser distinta de la actual',
            'newPassword.confirmed' => 'La confirmación de la contraseña no coincide',
        ];
    }
}
