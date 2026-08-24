<?php

namespace App\Http\Requests\Exchange;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'holidayDate' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'holidayDate.date_format' => 'La fecha debe tener el formato YYYY-MM-DD',
            'description.required' => 'La descripción del día feriado es obligatoria',
        ];
    }
}
