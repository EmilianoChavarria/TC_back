<?php

namespace App\Http\Requests\Exchange;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Captura por lote del calendario de un año.
 * Los elementos con `uuid` se actualizan; el resto se dan de alta.
 */
class BulkHolidaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('holidays') || !array_is_list($this->all())) {
            return;
        }

        // Se reemplaza la entrada: conservar las claves numéricas de la raíz
        // haría que reglas con parámetro numérico las tomaran como nombre de campo.
        $this->replace(['holidays' => $this->all()]);
    }

    public function rules(): array
    {
        return [
            'holidays' => ['required', 'array', 'min:1', 'max:200'],
            'holidays.*.uuid' => ['nullable', 'uuid'],
            'holidays.*.holidayDate' => ['required', 'date_format:Y-m-d'],
            'holidays.*.description' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'holidays.required' => 'Envíe al menos un día feriado',
            'holidays.*.holidayDate.date_format' => 'La fecha debe tener el formato YYYY-MM-DD',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function holidays(): array
    {
        return $this->validated()['holidays'];
    }
}
