<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditRecorder;
use App\Services\Exchange\ExchangeRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Proceso diario: trae la publicación FIX de Banxico, le aplica el factor de su
 * rango y deja el tipo de cambio de la fecha aplicable correspondiente.
 */
class SyncExchangeRateCommand extends Command
{
    protected $signature = 'exchange-rate:sync
                            {--date= : Fecha de referencia YYYY-MM-DD (por omisión, hoy)}
                            {--days= : Días hacia atrás a consultar}';

    protected $description = 'Sincroniza el tipo de cambio FIX de Banxico y aplica el factor vigente';

    public function handle(ExchangeRateService $rates, AuditRecorder $audit): int
    {
        $date = $this->option('date') ? Carbon::createFromFormat('Y-m-d', $this->option('date')) : null;
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;

        try {
            $result = $rates->sync($date, $days);
        } catch (Throwable $e) {
            // Un fallo del servicio externo no debe romper la programación: se
            // reporta y el siguiente intento recupera los días pendientes.
            Log::error('[ExchangeRate] la sincronización falló', ['error' => $e->getMessage()]);

            $audit->event(
                ExchangeRateService::EVENT_SYNC_FAILED,
                'exchangerates',
                null,
                'Sincronización con Banxico fallida',
                ['error' => $e->getMessage()],
            );

            $this->error('No se pudo sincronizar: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Publicaciones procesadas: {$result['processed']}");

        foreach ($result['dates'] as $applicableDate) {
            $this->line("  fecha aplicable {$applicableDate}");
        }

        return self::SUCCESS;
    }
}
