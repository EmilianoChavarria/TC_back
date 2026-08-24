<?php

return [
    'enabled' => (bool) env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Métodos HTTP que se registran
    |--------------------------------------------------------------------------
    | Por defecto sólo las solicitudes que modifican estado. Agregar 'GET' aquí
    | registra también las lecturas (mucho volumen: úsese sólo para diagnóstico).
    */
    'methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],

    /*
    |--------------------------------------------------------------------------
    | Rutas que no se registran
    |--------------------------------------------------------------------------
    | Patrones estilo Request::is(). Ruido puro: salud del servicio y la
    | renovación de token que el frontend dispara sola cada pocos minutos.
    */
    'except' => [
        'api/health',
        'api/auth/verify',
        'up',
    ],

    /*
    |--------------------------------------------------------------------------
    | Claves cuyo valor nunca se almacena
    |--------------------------------------------------------------------------
    | Se comparan sin distinguir mayúsculas y se sustituyen por el marcador de
    | abajo, tanto en el cuerpo de la solicitud como en los diffs de los modelos.
    */
    'redacted' => [
        'password',
        'password_confirmation',
        'currentPassword',
        'newPassword',
        'newPassword_confirmation',
        'passwordHash',
        'sessionToken',
        'token',
        'access_token',
        'apiKey',
        'secret',
        'authorization',
    ],

    'redaction_mask' => '[REDACTADO]',

    /*
    |--------------------------------------------------------------------------
    | Columnas que nunca entran al diff de un modelo
    |--------------------------------------------------------------------------
    | Ruido de sesión que cambiaría en cada petición. Se suma a lo que cada
    | modelo declare en $auditExclude.
    */
    'ignored_columns' => [
        'lastActivityAt',
        'lastKnownIp',
        'lastLoginAt',
        'lastFailedAt',
        'remember_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tamaño máximo del cuerpo almacenado, en caracteres del JSON resultante
    |--------------------------------------------------------------------------
    */
    'max_payload_chars' => 20000,

    /*
    | Etiqueta del actor cuando la acción no la origina una persona
    | (comandos de consola, tareas programadas, procesos internos).
    */
    'system_actor_label' => 'Proceso automático',
];
