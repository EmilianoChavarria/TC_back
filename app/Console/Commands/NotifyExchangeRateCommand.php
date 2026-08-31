<?php

namespace App\Console\Commands;

use App\Services\Notifications\ExchangeRateNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envío programado del aviso de tipo de cambio.
 *
 * Va aparte de `exchange-rate:sync` porque los dos responden a cosas
 * distintas: la sincronización corre cuando publica Banxico, al mediodía, y el
 * aviso cuando conviene que la lista lo lea. Atarlos obligaría a mover un
 * horario cada vez que se mueve el otro.
 *
 * El comando no decide si toca enviar: eso vive en `ExchangeRateNotifier`, que
 * descarta el valor ya avisado. Así la programación puede invocarlo sin
 * condiciones y una segunda corrida de recuperación no duplica el correo.
 */
class NotifyExchangeRateCommand extends Command
{
    protected $signature = 'exchange-rate:notify';

    protected $description = 'Envía a la lista el aviso del último tipo de cambio establecido';

    public function handle(ExchangeRateNotifier $notifier): int
    {
        try {
            $sent = $notifier->notifyLatest();
        } catch (Throwable $e) {
            // Un fallo del correo no debe romper la programación: se reporta y
            // la siguiente corrida lo reintenta, porque la fecha sigue sin
            // marcarse como avisada.
            Log::error('[ExchangeRate] el aviso programado falló', ['error' => $e->getMessage()]);

            $this->error('No se pudo enviar el aviso: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($sent
            ? 'Aviso enviado.'
            : 'Sin novedades que avisar: no hay tipo de cambio vigente o el valor ya se envió.');

        return self::SUCCESS;
    }
}
