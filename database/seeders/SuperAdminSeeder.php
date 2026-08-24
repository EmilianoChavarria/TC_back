<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserSecurity;
use App\Services\Audit\AuditContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Cuenta inicial de superadministrador.
 * Credenciales por .env: SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->where('roleName', Role::SUPERADMIN)->first();

        if (!$role) {
            $this->command?->warn('No existe el rol SUPERADMIN. Ejecute RoleSeeder primero.');

            return;
        }

        // El alta inicial del sistema no ensucia la línea de tiempo.
        app(AuditContext::class)->withoutAuditing(fn () => $this->seedSuperAdmin($role));
    }

    private function seedSuperAdmin(Role $role): void
    {
        $email = mb_strtolower((string) env('SUPERADMIN_EMAIL', 'superadmin@local.test'));
        $password = (string) env('SUPERADMIN_PASSWORD', 'Sup3rAdmin!2026');
        $now = Carbon::now();

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'fullName' => (string) env('SUPERADMIN_NAME', 'Super Administrador'),
                'passwordHash' => Hash::make($password),
                'roleId' => $role->id,
                'preferredLanguage' => 'es',
                'isActive' => true,
                'mustChangePassword' => true,
                'passwordChangedAt' => $now,
                'deletedAt' => null,
            ]
        );

        UserSecurity::query()->updateOrCreate(
            ['userId' => $user->id],
            ['failedAttempts' => 0, 'isBlocked' => false, 'blockedAt' => null, 'blockedReason' => null]
        );

        $this->command?->info("SUPERADMIN listo: {$email}");
    }
}
