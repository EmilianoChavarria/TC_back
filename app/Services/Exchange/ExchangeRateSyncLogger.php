<?php

namespace App\Services\Exchange;

use App\Models\ExchangeRateSyncRun;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Escribe la bitácora del proceso de sincronización con Banxico.
 *
 * La fila se abre ANTES de empezar, en estado `running`, y se cierra al
 * terminar. Escribirla sólo al final dejaría sin rastro la corrida que muere a
 * medias (timeout de PHP, proceso matado, caída del servidor), que es
 * justamente la que interesa investigar.
 *
 * Un fallo al registrar la bitácora nunca tumba la sincronización: el tipo de
 * cambio del día importa más que su bitácora.
 */
class ExchangeRateSyncLogger
{
    public function start(string $trigger, Carbon $referenceDate, int $lookbackDays, ?User $actor): ?ExchangeRateSyncRun
    {
        try {
            return ExchangeRateSyncRun::query()->create([
                'trigger' => $trigger,
                'status' => ExchangeRateSyncRun::STATUS_RUNNING,
                'referenceDate' => $referenceDate->toDateString(),
                'lookbackDays' => $lookbackDays,
                'userId' => $actor?->id,
                'actorName' => $actor?->fullName,
                'startedAt' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('[ExchangeRate] no se pudo abrir la bitácora de la corrida', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param array{processed: int, dates: array<int, string>, carried: array<int, array{date: string, from: string}>} $result
     */
    public function succeeded(?ExchangeRateSyncRun $run, array $result): void
    {
        $this->close($run, [
            'status' => ExchangeRateSyncRun::STATUS_SUCCESS,
            'processed' => $result['processed'],
            'carried' => count($result['carried']),
            'dates' => $result['dates'],
            'carriedDates' => $result['carried'],
        ]);
    }

    public function failed(?ExchangeRateSyncRun $run, Throwable $e): void
    {
        $this->close($run, [
            'status' => ExchangeRateSyncRun::STATUS_FAILED,
            'errorMessage' => $e->getMessage(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function close(?ExchangeRateSyncRun $run, array $attributes): void
    {
        if (!$run) {
            return;
        }

        try {
            $finishedAt = Carbon::now();

            $run->fill($attributes + [
                'finishedAt' => $finishedAt,
                'durationMs' => max(0, (int) $run->startedAt->diffInMilliseconds($finishedAt)),
            ])->save();
        } catch (Throwable $e) {
            Log::warning('[ExchangeRate] no se pudo cerrar la bitácora de la corrida', ['error' => $e->getMessage()]);
        }
    }
}
