<?php

namespace App\Http\Requests\Exchange;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Captura o corrección manual del tipo de cambio.
 * El motivo es obligatorio: queda en el historial para la aclaración posterior.
 */
class ManualExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'manualRate' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];

        // Sólo en el alta manual se recibe la fecha; al actualizar viene en la ruta.
        if ($this->isMethod('POST')) {
            $rules['applicableDate'] = ['required', 'date_format:Y-m-d'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'El motivo de la variación es obligatorio',
            'reason.min' => 'Describa el motivo de la variación con al menos 5 caracteres',
            'manualRate.gt' => 'El tipo de cambio debe ser mayor que cero',
        ];
    }
}
