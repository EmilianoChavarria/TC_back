<?php

namespace App\Http\Requests\Exchange;

use Illuminate\Foundation\Http\FormRequest;

class StoreFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rangeFrom' => ['required', 'numeric', 'min:0', 'max:999999', 'decimal:0,4'],
            'rangeTo' => ['required', 'numeric', 'gt:rangeFrom', 'max:999999', 'decimal:0,4'],
            'factor' => ['required', 'numeric', 'gt:0', 'max:999', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'rangeTo.gt' => 'El límite superior debe ser mayor que el inferior',
            'factor.gt' => 'El factor debe ser mayor que cero',
            'factor.decimal' => 'El factor admite hasta 3 decimales',
            'rangeFrom.decimal' => 'El límite inferior admite hasta 4 decimales',
            'rangeTo.decimal' => 'El límite superior admite hasta 4 decimales',
        ];
    }
}
