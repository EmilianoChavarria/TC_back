<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class JwtService
{
    private const ALGO = 'HS256';

    /**
     * El payload va en base64 dentro de la cookie y cualquiera puede leerlo:
     * sólo lleva el identificador público (uuid) y el nombre del rol, nunca ids
     * de base de datos.
     */
    public function issueToken(string $userUuid, ?string $roleName, int $ttlMinutes): string
    {
        $now = Carbon::now();

        $payload = [
            'iss' => (string) config('security.jwt_issuer'),
            'sub' => $userUuid,
            'roleName' => $roleName,
            'iat' => $now->timestamp,
            'nbf' => $now->timestamp,
            'exp' => $now->copy()->addMinutes(max(1, $ttlMinutes))->timestamp,
            'jti' => (string) Str::uuid7(),
        ];

        return JWT::encode($payload, $this->secret(), self::ALGO);
    }

    public function decodeToken(string $token): object
    {
        return JWT::decode($token, new Key($this->secret(), self::ALGO));
    }

    private function secret(): string
    {
        $secret = (string) config('security.jwt_secret');

        if (str_starts_with($secret, 'base64:')) {
            return (string) base64_decode(substr($secret, 7));
        }

        return $secret;
    }
}
