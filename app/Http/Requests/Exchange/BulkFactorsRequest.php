<?php

namespace App\Http\Requests\Exchange;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Guardado por lote de factores.
 *
 * Cada elemento con `uuid` actualiza el factor existente; sin `uuid` se da de
 * alta. Se acepta tanto `{"factors": [...]}` como un arreglo en la raíz.
 */
class BulkFactorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('factors') || !array_is_list($this->all())) {
            return;
        }

        // Se REEMPLAZA la entrada, no se fusiona: si se conservaran las claves
        // numéricas de la raíz, reglas como `gt:0` las tomarían como nombre de
        // campo (Laravel resuelve primero el parámetro como campo) y compararían
        // contra el elemento 0 en lugar de contra el número cero.
        $this->replace(['factors' => $this->all()]);
    }

    public function rules(): array
    {
        return [
            'factors' => ['required', 'array', 'min:1', 'max:200'],
            'factors.*.uuid' => ['nullable', 'uuid'],
            'factors.*.rangeFrom' => ['required', 'numeric', 'min:0', 'max:999999'],
            'factors.*.rangeTo' => ['required', 'numeric', 'gt:factors.*.rangeFrom', 'max:999999'],
            'factors.*.factor' => ['required', 'numeric', 'gt:0', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'factors.required' => 'Envíe al menos un factor',
            'factors.*.rangeTo.gt' => 'El límite superior debe ser mayor que el inferior',
            'factors.*.factor.gt' => 'El factor debe ser mayor que cero',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function factors(): array
    {
        return $this->validated()['factors'];
    }
}
