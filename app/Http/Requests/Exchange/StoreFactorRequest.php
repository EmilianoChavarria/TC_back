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
            'rangeFrom' => ['required', 'numeric', 'min:0', 'max:999999'],
            'rangeTo' => ['required', 'numeric', 'gt:rangeFrom', 'max:999999'],
            'factor' => ['required', 'numeric', 'gt:0', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'rangeTo.gt' => 'El límite superior debe ser mayor que el inferior',
            'factor.gt' => 'El factor debe ser mayor que cero',
        ];
    }
}
