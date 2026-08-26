<?php

namespace App\Http\Requests\Exchange;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rangeFrom' => ['sometimes', 'numeric', 'min:0', 'max:999999', 'decimal:0,4'],
            'rangeTo' => ['sometimes', 'numeric', 'max:999999', 'decimal:0,4'],
            'factor' => ['sometimes', 'numeric', 'gt:0', 'max:999', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'factor.decimal' => 'El factor admite hasta 3 decimales',
            'rangeFrom.decimal' => 'El límite inferior admite hasta 4 decimales',
            'rangeTo.decimal' => 'El límite superior admite hasta 4 decimales',
        ];
    }
}
