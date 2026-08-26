<?php

namespace App\Console\Commands;

use App\Services\Exchange\ExchangeRateService;
use Illuminate\Console\Command;

/**
 * Limpieza posterior al retiro del cálculo publicación × factor.
 *
 * Quita las capturas manuales del histórico y deja como vigente la publicación
 * de Banxico de cada registro. De paso normaliza los registros automáticos que
 * todavía guardan el producto viejo.
 */
class ResetManualExchangeRatesCommand extends Command
{
    protected $signature = 'exchange-rate:reset-manual
                            {--dry-run : Sólo informa lo que haría, sin escribir}
                            {--drop-orphans : Marca como eliminados los registros manuales sin publicación de Banxico}';

    protected $description = 'Elimina las capturas manuales y deja el tipo de cambio de Banxico como vigente';

    public function handle(ExchangeRateService $rates): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $dropOrphans = (bool) $this->option('drop-orphans');

        $result = $rates->clearManualOverrides($dropOrphans, $dryRun);

        if ($dryRun) {
            $this->warn('Simulación: no se escribió nada.');
        }

        $this->info("Capturas manuales eliminadas: {$result['cleared']}");
        $this->info("Registros automáticos normalizados: {$result['normalized']}");

        if ($result['orphans'] !== []) {
            $this->newLine();
            $this->warn('Registros manuales sin publicación de Banxico ('.count($result['orphans']).'):');

            foreach ($result['orphans'] as $date) {
                $this->line("  {$date}");
            }

            if ($dropOrphans) {
                $this->info("Marcados como eliminados: {$result['orphansDropped']}");
            } else {
                $this->line('No se tocaron. Use --drop-orphans para marcarlos como eliminados.');
            }
        }

        return self::SUCCESS;
    }
}
