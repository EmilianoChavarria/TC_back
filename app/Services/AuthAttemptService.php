<?php

namespace App\Services;

use App\Models\BlockedIp;
use App\Models\IpBlockedHistory;
use App\Models\User;
use App\Models\UserBlockedHistory;
use App\Models\UserSecurity;
use Illuminate\Support\Carbon;

/**
 * Conteo de fallos de autenticación y bloqueo/desbloqueo de usuarios e IPs.
 *
 * Los fallos se cuentan dentro de una ventana deslizante
 * (config security.attempt_window_hours, 24 h por defecto): un fallo posterior
 * a la ventana reinicia el contador.
 *
 * Un bloqueo NO caduca solo: sólo un SUPERADMIN o ADMIN puede liberarlo.
 */
class AuthAttemptService
{
    public function windowStart(): Carbon
    {
        return Carbon::now()->subHours(max(1, (int) config('security.attempt_window_hours')));
    }

    // ---------------------------------------------------------------- usuarios

    public function userSecurity(int $userId): UserSecurity
    {
        return UserSecurity::query()->firstOrCreate(
            ['userId' => $userId],
            ['failedAttempts' => 0, 'isBlocked' => false]
        );
    }

    public function isUserBlocked(int $userId): bool
    {
        return (bool) $this->userSecurity($userId)->isBlocked;
    }

    /**
     * Registra un fallo del usuario y lo bloquea al alcanzar el umbral.
     *
     * @return bool true si el usuario quedó bloqueado con este intento
     */
    public function registerUserFailure(User $user, int $maxAttempts, string $ip): bool
    {
        $security = $this->userSecurity((int) $user->id);
        $now = Carbon::now();

        $withinWindow = $security->lastFailedAt && $security->lastFailedAt->greaterThanOrEqualTo($this->windowStart());
        $attempts = $withinWindow ? (int) $security->failedAttempts + 1 : 1;
        $wasBlocked = (bool) $security->isBlocked;

        $security->failedAttempts = $attempts;
        $security->lastFailedAt = $now;
        $security->lastKnownIp = $ip;

        $mustBlock = $attempts >= $maxAttempts;

        if ($mustBlock && !$wasBlocked) {
            $security->isBlocked = true;
            $security->blockedAt = $now;
            $security->blockedReason = "Máximo de fallos de autenticación en {$this->windowHours()} h";
        }

        $security->save();

        if ($mustBlock && !$wasBlocked) {
            UserBlockedHistory::create([
                'userId' => $user->id,
                'action' => 'blocked',
                'reason' => $security->blockedReason,
                'failedAttempts' => $attempts,
                'ipAddress' => $ip,
                'adminUserId' => null,
                'createdAt' => $now,
            ]);

            return true;
        }

        return $wasBlocked;
    }

    public function resetUserFailures(int $userId, string $ip): void
    {
        $security = $this->userSecurity($userId);

        $security->fill([
            'failedAttempts' => 0,
            'lastFailedAt' => null,
            'lastKnownIp' => $ip,
        ])->save();
    }

    public function unblockUser(int $userId, ?int $adminUserId, string $reason = 'Desbloqueo manual por administrador'): bool
    {
        $security = UserSecurity::query()->where('userId', $userId)->first();

        if (!$security) {
            return false;
        }

        $security->fill([
            'failedAttempts' => 0,
            'lastFailedAt' => null,
            'isBlocked' => false,
            'blockedAt' => null,
            'blockedReason' => null,
        ])->save();

        UserBlockedHistory::create([
            'userId' => $userId,
            'action' => 'unblocked',
            'reason' => $reason,
            'failedAttempts' => 0,
            'ipAddress' => null,
            'adminUserId' => $adminUserId,
            'createdAt' => Carbon::now(),
        ]);

        return true;
    }

    // --------------------------------------------------------------------- IPs

    public function ipRecord(string $ip): BlockedIp
    {
        return BlockedIp::query()->firstOrCreate(
            ['ipAddress' => $ip],
            ['failedAttempts' => 0, 'isBlockedPermanently' => false]
        );
    }

    public function isIpBlocked(string $ip): bool
    {
        return BlockedIp::query()
            ->where('ipAddress', $ip)
            ->where('isBlockedPermanently', true)
            ->exists();
    }

    /**
     * Registra un fallo de la IP y la bloquea al alcanzar el umbral.
     * Aplica a todos los roles, incluidos SUPERADMIN y ADMIN.
     *
     * @return bool true si la IP quedó bloqueada con este intento
     */
    public function registerIpFailure(string $ip, int $maxAttempts, ?int $userId = null): bool
    {
        $record = $this->ipRecord($ip);
        $now = Carbon::now();

        $withinWindow = $record->lastFailedAt && $record->lastFailedAt->greaterThanOrEqualTo($this->windowStart());
        $attempts = $withinWindow ? (int) $record->failedAttempts + 1 : 1;
        $wasBlocked = (bool) $record->isBlockedPermanently;

        $record->failedAttempts = $attempts;
        $record->lastFailedAt = $now;

        $mustBlock = $attempts >= $maxAttempts;

        if ($mustBlock && !$wasBlocked) {
            $record->isBlockedPermanently = true;
            $record->blockedAt = $now;
            $record->releasedAt = null;
        }

        $record->save();

        if ($mustBlock && !$wasBlocked) {
            IpBlockedHistory::create([
                'ipAddress' => $ip,
                'action' => 'blocked',
                'reason' => "Máximo de fallos de autenticación en {$this->windowHours()} h",
                'failedAttempts' => $attempts,
                'userId' => $userId,
                'adminUserId' => null,
                'createdAt' => $now,
            ]);

            return true;
        }

        return $wasBlocked;
    }

    public function resetIpFailures(string $ip): void
    {
        $record = BlockedIp::query()->where('ipAddress', $ip)->first();

        if (!$record || (bool) $record->isBlockedPermanently) {
            return;
        }

        $record->fill(['failedAttempts' => 0, 'lastFailedAt' => null])->save();
    }

    public function unblockIp(string $ip, ?int $adminUserId, string $reason = 'Desbloqueo manual por administrador'): bool
    {
        $record = BlockedIp::query()->where('ipAddress', $ip)->first();

        if (!$record) {
            return false;
        }

        $record->fill([
            'failedAttempts' => 0,
            'lastFailedAt' => null,
            'isBlockedPermanently' => false,
            'releasedAt' => Carbon::now(),
        ])->save();

        IpBlockedHistory::create([
            'ipAddress' => $ip,
            'action' => 'unblocked',
            'reason' => $reason,
            'failedAttempts' => 0,
            'userId' => null,
            'adminUserId' => $adminUserId,
            'createdAt' => Carbon::now(),
        ]);

        return true;
    }

    private function windowHours(): int
    {
        return max(1, (int) config('security.attempt_window_hours'));
    }
}
