<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT
    |--------------------------------------------------------------------------
    | El TTL efectivo del token es el "sessionTimeoutMinutes" configurado en
    | base de datos (Configuración del Sistema). El valor de aquí sólo se usa
    | como respaldo cuando aún no existe configuración.
    */
    // Un JWT_SECRET vacío en el .env cae de vuelta a APP_KEY.
    'jwt_secret' => env('JWT_SECRET') ?: env('APP_KEY'),
    'jwt_issuer' => env('JWT_ISSUER', 'tcproject-backend'),
    'jwt_ttl_minutes' => (int) env('JWT_TTL_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Cookie de acceso (httpOnly)
    |--------------------------------------------------------------------------
    */
    'cookie_name' => env('AUTH_COOKIE_NAME', 'access_token'),
    'cookie_domain' => env('AUTH_COOKIE_DOMAIN'),
    'cookie_secure' => env('AUTH_COOKIE_SECURE', env('APP_ENV') === 'production'),
    'cookie_same_site' => env('AUTH_COOKIE_SAME_SITE', 'Lax'),

    /*
    |--------------------------------------------------------------------------
    | Umbrales de bloqueo (respaldo si no hay registro en loginattemptsettings)
    |--------------------------------------------------------------------------
    */
    'max_user_attempts' => (int) env('SECURITY_MAX_USER_ATTEMPTS', 5),
    'max_ip_attempts' => (int) env('SECURITY_MAX_IP_ATTEMPTS', 12),
    'session_timeout_minutes' => (int) env('SECURITY_SESSION_TIMEOUT_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Ventana de conteo de fallos, en horas
    |--------------------------------------------------------------------------
    | Los fallos se cuentan dentro de esta ventana deslizante. Fuera de ella el
    | contador se reinicia.
    */
    'attempt_window_hours' => (int) env('SECURITY_ATTEMPT_WINDOW_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Requisitos de contraseña (respaldo si no hay registro en BD)
    |--------------------------------------------------------------------------
    */
    'password_defaults' => [
        'minLength' => 10,
        'requireUppercase' => true,
        'requireLowercase' => true,
        'requireNumbers' => true,
        'requireSpecialChars' => true,
        'allowedSpecialChars' => '!#$%&*?',
        'expirationDays' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | URL del frontend (enlace incluido en los correos)
    |--------------------------------------------------------------------------
    */
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];
