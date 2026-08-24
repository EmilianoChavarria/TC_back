<?php

namespace App\Console\Commands;

use App\Services\AuthAttemptService;
use Illuminate\Console\Command;

/**
 * Salida de emergencia por consola cuando nadie puede entrar desde la IP bloqueada.
 */
class UnlockIpCommand extends Command
{
    protected $signature = 'security:unlock-ip {ip : Dirección IP a liberar}';

    protected $description = 'Libera una dirección IP bloqueada';

    public function handle(AuthAttemptService $attempts): int
    {
        $ip = (string) $this->argument('ip');

        if (!$attempts->unblockIp($ip, null, 'Desbloqueo manual desde consola')) {
            $this->error("La dirección {$ip} no tiene registro de bloqueo.");

            return self::FAILURE;
        }

        $this->info("Dirección IP {$ip} desbloqueada.");

        return self::SUCCESS;
    }
}
