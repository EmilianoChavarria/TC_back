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
            'rangeFrom' => ['sometimes', 'numeric', 'min:0', 'max:999999'],
            'rangeTo' => ['sometimes', 'numeric', 'max:999999'],
            'factor' => ['sometimes', 'numeric', 'gt:0', 'max:999'],
        ];
    }
}
