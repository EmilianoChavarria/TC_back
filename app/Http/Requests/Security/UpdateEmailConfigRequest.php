<?php

namespace App\Http\Requests\Security;

use App\Models\EmailConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emailSupport' => ['required', 'email', 'max:255'],
            'emailMode' => ['required', Rule::in([
                EmailConfig::MODE_NORMAL,
                EmailConfig::MODE_OVERRIDE,
                EmailConfig::MODE_DISABLED,
            ])],
            'overrideEmail' => ['nullable', 'email', 'max:255', 'required_if:emailMode,'.EmailConfig::MODE_OVERRIDE],
        ];
    }

    public function messages(): array
    {
        return [
            'overrideEmail.required_if' => 'El modo override requiere un correo de destino',
        ];
    }
}
