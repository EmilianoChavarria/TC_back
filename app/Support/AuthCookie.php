<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;

class AuthCookie
{
    public static function name(): string
    {
        return (string) config('security.cookie_name', 'access_token');
    }

    /**
     * Cookie httpOnly con el JWT. No es legible por JavaScript.
     */
    public static function make(string $token, int $minutes): Cookie
    {
        return cookie(
            self::name(),
            $token,
            max(1, $minutes),
            '/',
            config('security.cookie_domain'),
            (bool) config('security.cookie_secure'),
            true,                                    // httpOnly
            false,                                   // raw
            (string) config('security.cookie_same_site', 'Lax')
        );
    }

    public static function forget(): Cookie
    {
        return cookie()->forget(self::name(), '/', config('security.cookie_domain'));
    }
}
