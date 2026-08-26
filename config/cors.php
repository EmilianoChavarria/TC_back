<?php

return [

    /*
     | Sólo la API. `web.php` no expone nada consumido desde otro origen.
     */
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
     | Con cookies httpOnly NO se puede usar "*": el navegador exige un origen
     | explícito cuando supports_credentials está activo.
     */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200,http://127.0.0.1:4200'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    /*
     | El JWT viaja en cookie httpOnly, así que el cliente no necesita leer
     | ninguna cabecera de la respuesta.
     */
    'exposed_headers' => [],

    /*
     | Cachea el preflight 24 h: evita un OPTIONS por cada solicitud del SPA.
     */
    'max_age' => (int) env('CORS_MAX_AGE', 86400),

    'supports_credentials' => true,

];
