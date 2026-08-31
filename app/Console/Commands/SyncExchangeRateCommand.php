<?php

namespace App\Console\Commands;

use App\Models\ExchangeRateSyncRun;
use App\Services\Audit\AuditRecorder;
use App\Services\Exchange\ExchangeRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Proceso diario: trae la publicación FIX de Banxico y la deja como tipo de
 * cambio de la fecha aplicable, junto con el factor informativo de su rango.
 */
class SyncExchangeRateCommand extends Command
{
    protected $signature = 'exchange-rate:sync
                            {--date= : Fecha de referencia YYYY-MM-DD (por omisión, hoy)}
                            {--days= : Días hacia atrás a consultar}
                            {--trigger=console : Origen de la corrida: scheduled o console}';

    protected $description = 'Sincroniza el tipo de cambio FIX de Banxico y registra el factor de su rango';

    public function handle(ExchangeRateService $rates, AuditRecorder $audit): int
    {
        $date = $this->option('date') ? Carbon::createFromFormat('Y-m-d', $this->option('date')) : null;
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;

        try {
            $result = $rates->sync($date, $days, $this->trigger());
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

        foreach ($result['carried'] as $carried) {
            $this->line("  feriado {$carried['date']} conserva el TC del {$carried['from']}");
        }

        return self::SUCCESS;
    }

    /**
     * La programación diaria pasa `--trigger=scheduled`. Sin ese dato la
     * bitácora no distinguiría una corrida automática de una invocación suelta
     * en la terminal, que es lo primero que se pregunta cuando un día falta.
     */
    private function trigger(): string
    {
        return $this->option('trigger') === ExchangeRateSyncRun::TRIGGER_SCHEDULED
            ? ExchangeRateSyncRun::TRIGGER_SCHEDULED
            : ExchangeRateSyncRun::TRIGGER_CONSOLE;
    }
}
