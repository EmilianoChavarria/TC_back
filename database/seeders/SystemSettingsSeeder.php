<?php

namespace Database\Seeders;

use App\Models\EmailConfig;
use App\Models\LoginAttemptSetting;
use App\Models\PasswordRequirement;
use App\Services\Audit\AuditContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Valores iniciales de la pantalla "Configuración del Sistema".
 */
class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        app(AuditContext::class)->withoutAuditing(fn () => $this->seedSettings());
    }

    private function seedSettings(): void
    {
        $now = Carbon::now();

        if (!PasswordRequirement::query()->exists()) {
            PasswordRequirement::create(config('security.password_defaults') + [
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
        }

        if (!LoginAttemptSetting::query()->exists()) {
            LoginAttemptSetting::create([
                'maxUserAttempts' => (int) config('security.max_user_attempts'),
                'maxIpAttempts' => (int) config('security.max_ip_attempts'),
                'sessionTimeoutMinutes' => (int) config('security.session_timeout_minutes'),
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
        }

        if (!EmailConfig::query()->exists()) {
            EmailConfig::create([
                'emailSupport' => (string) env('MAIL_SUPPORT_ADDRESS', config('mail.from.address')),
                'emailMode' => (string) env('EMAIL_MODE', EmailConfig::MODE_NORMAL),
                'overrideEmail' => env('EMAIL_OVERRIDE_ADDRESS'),
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
        }
    }
}
