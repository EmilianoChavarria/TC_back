<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuthAttemptService;
use Illuminate\Console\Command;

class UnlockUserCommand extends Command
{
    protected $signature = 'security:unlock-user {email : Correo del usuario a desbloquear}';

    protected $description = 'Libera un usuario bloqueado por intentos fallidos';

    public function handle(AuthAttemptService $attempts): int
    {
        $email = mb_strtolower((string) $this->argument('email'));
        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            $this->error("No existe un usuario con el correo {$email}.");

            return self::FAILURE;
        }

        $attempts->unblockUser((int) $user->id, null, 'Desbloqueo manual desde consola');
        $this->info("Usuario {$email} desbloqueado.");

        return self::SUCCESS;
    }
}
