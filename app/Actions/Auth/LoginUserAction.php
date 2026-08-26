<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\AuthAttemptService;
use App\Services\JwtService;
use App\Services\LoginAttemptSettingsService;
use App\Services\PasswordValidationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class LoginUserAction
{
    public function __construct(
        private readonly LoginAttemptSettingsService $settingsService,
        private readonly AuthAttemptService $attempts,
        private readonly PasswordValidationService $passwords,
        private readonly JwtService $jwt,
    ) {
    }

    /**
     * @param array{email: string, password: string} $data
     * @return array{ok: bool, status: int, message: string, token?: string, sessionTimeoutMinutes?: int, user?: array}
     */
    public function execute(array $data, string $ip): array
    {
        $settings = $this->settingsService->getSettings();
        $maxUserAttempts = (int) $settings->maxUserAttempts;
        $maxIpAttempts = (int) $settings->maxIpAttempts;
        $sessionTimeoutMinutes = max(1, (int) $settings->sessionTimeoutMinutes);

        // La IP bloqueada corta el flujo para cualquier rol, sin excepción.
        if ($this->attempts->isIpBlocked($ip)) {
            return $this->fail(423, 'Dirección IP bloqueada. Contacte al administrador.');
        }

        $user = User::query()
            ->with('role')
            ->where('email', $data['email'])
            ->first();

        if (!$user || !$user->isActive || $user->deletedAt) {
            $this->attempts->registerIpFailure($ip, $maxIpAttempts, $user?->id);

            return $this->fail(401, 'Credenciales inválidas');
        }

        if ($this->attempts->isUserBlocked((int) $user->id)) {
            $this->attempts->registerIpFailure($ip, $maxIpAttempts, (int) $user->id);

            return $this->fail(423, 'Usuario bloqueado. Contacte al administrador para desbloquearlo.');
        }

        if (!Hash::check((string) $data['password'], (string) $user->passwordHash)) {
            // SUPERADMIN y ADMIN nunca se bloquean por intentos fallidos; la IP sí.
            $userBlocked = $user->isBlockExempt()
                ? false
                : $this->attempts->registerUserFailure($user, $maxUserAttempts, $ip);

            $ipBlocked = $this->attempts->registerIpFailure($ip, $maxIpAttempts, (int) $user->id);

            if ($ipBlocked) {
                return $this->fail(423, 'Dirección IP bloqueada por exceso de intentos fallidos.');
            }

            if ($userBlocked) {
                return $this->fail(423, 'Usuario bloqueado por exceso de intentos fallidos. Contacte al administrador.');
            }

            return $this->fail(401, 'Credenciales inválidas');
        }

        $now = Carbon::now();

        $this->attempts->resetUserFailures((int) $user->id, $ip);
        $this->attempts->resetIpFailures($ip);

        // Vigencia de la contraseña: si caducó se permite entrar, pero el
        // middleware sólo dejará pasar el cambio de contraseña y el logout.
        $passwordExpired = $this->passwords->isExpired($user->passwordChangedAt);

        if ($passwordExpired && !$user->mustChangePassword) {
            $user->mustChangePassword = true;
            $user->save();
        }

        $roleName = $user->roleName();
        $token = $this->jwt->issueToken((string) $user->uuid, $roleName, $sessionTimeoutMinutes);

        $security = $this->attempts->userSecurity((int) $user->id);
        $security->fill([
            'sessionToken' => $token,
            'lastActivityAt' => $now,
            'lastLoginAt' => $now,
            'lastKnownIp' => $ip,
        ])->save();

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Login exitoso',
            'token' => $token,
            'sessionTimeoutMinutes' => $sessionTimeoutMinutes,
            'user' => [
                'uuid' => (string) $user->uuid,
                'fullName' => (string) $user->fullName,
                'email' => (string) $user->email,
                'roleName' => $roleName,
                'mustChangePassword' => (bool) $user->mustChangePassword,
                'passwordExpired' => $passwordExpired,
                'passwordExpiresAt' => $this->passwords->expiresAt($user->passwordChangedAt)?->toIso8601String(),
            ],
        ];
    }

    private function fail(int $status, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'message' => $message];
    }
}
