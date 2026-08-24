<?php

namespace App\Services;

use App\Models\PasswordRequirement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PasswordValidationService
{
    /**
     * Requisitos vigentes. Si no hay registro en BD devuelve los valores de
     * config/security.php sin persistir nada.
     */
    public function getRequirements(): PasswordRequirement
    {
        $requirements = PasswordRequirement::query()->orderBy('id')->first();

        if ($requirements) {
            return $requirements;
        }

        return new PasswordRequirement(config('security.password_defaults'));
    }

    /**
     * @return string[] lista de incumplimientos; vacía si la contraseña es válida
     */
    public function validatePassword(string $password, ?PasswordRequirement $requirements = null): array
    {
        $requirements = $requirements ?? $this->getRequirements();
        $errors = [];

        if (mb_strlen($password) < (int) $requirements->minLength) {
            $errors[] = "La contraseña debe tener al menos {$requirements->minLength} caracteres";
        }

        if ($requirements->requireUppercase && !preg_match('/\p{Lu}/u', $password)) {
            $errors[] = 'La contraseña debe contener al menos una letra mayúscula';
        }

        if ($requirements->requireLowercase && !preg_match('/\p{Ll}/u', $password)) {
            $errors[] = 'La contraseña debe contener al menos una letra minúscula';
        }

        if ($requirements->requireNumbers && !preg_match('/\d/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un número';
        }

        if ($requirements->requireSpecialChars) {
            $allowed = (string) $requirements->allowedSpecialChars;

            if ($allowed !== '' && strpbrk($password, $allowed) === false) {
                $errors[] = "La contraseña debe contener al menos uno de estos caracteres especiales: {$allowed}";
            }
        }

        return $errors;
    }

    public function isPasswordValid(string $password, ?PasswordRequirement $requirements = null): bool
    {
        return $this->validatePassword($password, $requirements) === [];
    }

    /**
     * ¿La contraseña caducó según la vigencia configurada?
     * expirationDays = 0 significa "sin vencimiento".
     */
    public function isExpired(?Carbon $passwordChangedAt, ?PasswordRequirement $requirements = null): bool
    {
        $requirements = $requirements ?? $this->getRequirements();
        $days = (int) $requirements->expirationDays;

        if ($days <= 0) {
            return false;
        }

        if (!$passwordChangedAt) {
            return true;
        }

        return $passwordChangedAt->copy()->addDays($days)->isPast();
    }

    public function expiresAt(?Carbon $passwordChangedAt, ?PasswordRequirement $requirements = null): ?Carbon
    {
        $requirements = $requirements ?? $this->getRequirements();
        $days = (int) $requirements->expirationDays;

        if ($days <= 0 || !$passwordChangedAt) {
            return null;
        }

        return $passwordChangedAt->copy()->addDays($days);
    }

    /**
     * Genera una contraseña temporal que cumple los requisitos vigentes.
     */
    public function generateCompliantPassword(?PasswordRequirement $requirements = null): string
    {
        $requirements = $requirements ?? $this->getRequirements();
        $special = (string) ($requirements->allowedSpecialChars ?: '!#$%&*?');

        do {
            $password = Str::upper(Str::random(3))
                . Str::lower(Str::random(3))
                . random_int(100000, 999999)
                . $special[random_int(0, strlen($special) - 1)];

            $password = str_pad($password, max((int) $requirements->minLength, 12), (string) random_int(0, 9));
        } while (!$this->isPasswordValid($password, $requirements));

        return $password;
    }
}
