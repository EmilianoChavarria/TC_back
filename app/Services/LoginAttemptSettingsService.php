<?php

namespace App\Services;

use App\Models\LoginAttemptSetting;

class LoginAttemptSettingsService
{
    /**
     * Configuración vigente. Si aún no existe registro en BD, devuelve los
     * valores de config/security.php sin persistir nada.
     */
    public function getSettings(): LoginAttemptSetting
    {
        $settings = LoginAttemptSetting::query()->orderBy('id')->first();

        if ($settings) {
            return $settings;
        }

        return new LoginAttemptSetting([
            'maxUserAttempts' => (int) config('security.max_user_attempts'),
            'maxIpAttempts' => (int) config('security.max_ip_attempts'),
            'sessionTimeoutMinutes' => (int) config('security.session_timeout_minutes'),
        ]);
    }

    public function sessionTimeoutMinutes(): int
    {
        return max(1, (int) $this->getSettings()->sessionTimeoutMinutes);
    }
}
