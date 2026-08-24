<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Años que se administran
    |--------------------------------------------------------------------------
    | La pantalla trabaja con el año en curso y los siguientes. `years_ahead`
    | define cuántos años hacia adelante se pueden capturar.
    */
    'years_ahead' => (int) env('HOLIDAYS_YEARS_AHEAD', 1),

    /*
    |--------------------------------------------------------------------------
    | Recordatorio de captura del año siguiente
    |--------------------------------------------------------------------------
    | A partir de `start` (día y mes) el sistema envía un recordatorio diario por
    | correo a todos los usuarios activos, hasta que se capture el primer día
    | feriado del año siguiente.
    */
    'reminder' => [
        'enabled' => (bool) env('HOLIDAYS_REMINDER_ENABLED', true),
        'start' => env('HOLIDAYS_REMINDER_START', '11-15'),
        'hour' => env('HOLIDAYS_REMINDER_HOUR', '07:00'),
    ],
];
