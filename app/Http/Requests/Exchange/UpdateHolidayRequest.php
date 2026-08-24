<?php

namespace App\Http\Requests\Exchange;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'holidayDate' => ['sometimes', 'date_format:Y-m-d'],
            'description' => ['sometimes', 'string', 'max:150'],
        ];
    }
}
