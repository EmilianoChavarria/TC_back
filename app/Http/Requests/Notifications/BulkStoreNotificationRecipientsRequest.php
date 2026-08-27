<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de varios correos de una vez.
 *
 * El separador `;` se resuelve en el navegador y aquí llega ya como lista:
 * partir la cadena en los dos lados dejaría dos reglas que se pueden separar
 * sin que nadie lo note.
 */
class BulkStoreNotificationRecipientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emails' => ['required', 'array', 'min:1', 'max:200'],

            // ⚠️ Sin `email` en la regla de cada elemento: una sola dirección
            // mal escrita en una lista de treinta rechazaría el lote entero.
            // El servicio separa las válidas de las que no y devuelve ambas,
            // que es lo que permite pegar una lista real sin depurarla antes.
            'emails.*' => ['required', 'string', 'max:190'],
        ];
    }

    public function messages(): array
    {
        return [
            'emails.required' => 'Escriba al menos un correo',
            'emails.max' => 'No se pueden agregar más de 200 correos a la vez',
        ];
    }

    /** @return array<int, string> */
    public function emails(): array
    {
        return array_values((array) $this->input('emails', []));
    }
}
