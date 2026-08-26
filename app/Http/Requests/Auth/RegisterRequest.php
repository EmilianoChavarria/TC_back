<?php

namespace App\Http\Requests\Auth;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // El alta habitual es de usuarios: el rol se asume cuando no se envía.
        $roleName = trim((string) $this->input('roleName'));

        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'roleName' => $roleName === '' ? Role::USER : mb_strtoupper($roleName),
        ]);
    }

    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150'],
            // Opcional: si se omite se genera una contraseña temporal válida.
            'password' => ['nullable', 'string', 'max:255'],
            // Los roles son fijos: se recibe el nombre, nunca un id.
            'roleName' => ['required', Rule::in(Role::ALL)],
        ];
    }

    public function messages(): array
    {
        return [
            'roleName.in' => 'El rol debe ser uno de: '.implode(', ', Role::ALL),
        ];
    }
}
