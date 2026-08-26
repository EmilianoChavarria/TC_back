<?php

namespace App\Http\Requests\Users;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }

        if ($this->has('roleName')) {
            $this->merge(['roleName' => mb_strtoupper(trim((string) $this->input('roleName')))]);
        }
    }

    public function rules(): array
    {
        return [
            'fullName' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:150'],
            'roleName' => ['sometimes', Rule::in(Role::ALL)],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'roleName.in' => 'El rol debe ser uno de: '.implode(', ', Role::ALL),
        ];
    }
}
