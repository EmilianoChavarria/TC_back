<?php

namespace App\Support;

/**
 * Construye enlaces al SPA para los correos.
 *
 * El frontend usa enrutado por hash (`withHashLocation`), así que las rutas
 * internas cuelgan de `/#/`. La raíz sin hash es la consulta pública, no el
 * portal: enlazar ahí dejaría al usuario fuera de la sesión.
 */
class FrontendUrl
{
    public static function base(): string
    {
        return rtrim((string) config('security.frontend_url'), '/');
    }

    public static function to(string $path = ''): string
    {
        $path = trim($path, '/');

        return $path === '' ? self::base().'/' : self::base().'/#/'.$path;
    }
}
