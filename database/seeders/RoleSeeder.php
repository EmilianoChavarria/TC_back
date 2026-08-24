<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Services\Audit\AuditContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Los roles no son dinámicos: el sistema sólo reconoce estos tres.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(AuditContext::class)->withoutAuditing(fn () => $this->seedRoles());
    }

    private function seedRoles(): void
    {
        $now = Carbon::now();

        $roles = [
            Role::SUPERADMIN => 'Control total del sistema. Nunca se bloquea su cuenta por intentos fallidos.',
            Role::ADMIN => 'Administra usuarios y seguridad. Nunca se bloquea su cuenta por intentos fallidos.',
            Role::USER => 'Usuario estándar de la plataforma.',
        ];

        foreach ($roles as $name => $description) {
            Role::query()->updateOrCreate(
                ['roleName' => $name],
                [
                    'description' => $description,
                    'isActive' => true,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]
            );
        }
    }
}
