<?php

namespace App\Services\Exchange;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Notifications\ExchangeRateNotifier;
use App\Support\Decimals;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Cálculo y captura del tipo de cambio.
 *
 * Regla del negocio: la publicación de Banxico de un día hábil aplica al día
 * hábil siguiente. El tipo de cambio vigente ES esa publicación: no se le
 * aplica ninguna operación. El factor cuyo rango contiene a la publicación se
 * resuelve y se guarda sólo como dato informativo, para mostrarlo junto al
 * tipo de cambio.
 *
 * La captura manual nunca pisa la publicación: se guarda aparte y prevalece
 * como vigente, incluso si la sincronización automática llega después.
 */
class ExchangeRateService
{
    /** Eventos de la línea de tiempo que produce este servicio. */
    public const EVENT_SYNC = 'exchangeRate.sync';
    public const EVENT_SYNC_FAILED = 'exchangeRate.syncFailed';
    public const EVENT_MANUAL_OVERRIDE = 'exchangeRate.manualOverride';
    public const EVENT_MANUAL_RESET = 'exchangeRate.manualReset';

    public function __construct(
        private readonly BanxicoFixService $banxico,
        private readonly BusinessDayService $businessDays,
        private readonly ExchangeRateFactorService $factors,
        private readonly AuditRecorder $audit,
        private readonly ExchangeRateNotifier $notifier,
    ) {
    }

    /**
     * Trae las publicaciones recientes y actualiza las fechas aplicables.
     *
     * @return array{processed: int, dates: array<int, string>}
     */
    public function sync(?Carbon $referenceDate = null, ?int $lookbackDays = null): array
    {
        $referenceDate = ($referenceDate ?? Carbon::today())->copy()->startOfDay();
        $lookbackDays = $lookbackDays ?? (int) config('exchange.banxico.lookback_days', 7);

        $publications = $this->banxico->publicationsBetween(
            $referenceDate->copy()->subDays(max(1, $lookbackDays)),
            $referenceDate
        );

        $dates = [];

        foreach ($publications as $publication) {
            $rate = $this->applyPublication($publication['date'], $publication['rate']);
            $dates[] = $rate->applicableDate->toDateString();

            // El notificador decide si toca enviar: descarta fechas pasadas de
            // la ventana de recuperación y no repite un valor ya avisado.
            $this->notifier->notify($rate);
        }

        // Deja constancia de la corrida: es lo que alimenta el estado del
        // proceso automático en el tablero.
        $this->audit->event(
            self::EVENT_SYNC,
            'exchangerates',
            null,
            'Sincronización con Banxico',
            ['processed' => count($dates), 'dates' => $dates],
        );

        return ['processed' => count($dates), 'dates' => $dates];
    }

    /**
     * Guarda una publicación en la fecha aplicable que le toca (el día hábil
     * siguiente al de la publicación), junto con el factor informativo de su
     * rango.
     */
    public function applyPublication(Carbon $publishedDate, string $publishedRate): ExchangeRate
    {
        $applicableDate = $this->businessDays->nextBusinessDay($publishedDate);
        $factor = $this->factors->resolveFor($publishedRate);
        $now = Carbon::now();

        if (!$factor) {
            // El factor es informativo, pero si ningún rango cubre la
            // publicación queda registrado para que el administrador lo cubra.
            Log::warning('[ExchangeRate] ninguna clave de factor cubre la publicación', [
                'publishedDate' => $publishedDate->toDateString(),
                'publishedRate' => $publishedRate,
            ]);
        }

        // El tipo de cambio del proceso automático es la publicación tal cual.
        $automatic = $this->round($publishedRate);

        $rate = ExchangeRate::query()->where('applicableDate', $applicableDate->toDateString())->first();

        $attributes = [
            'publishedRate' => Decimals::roundRate($publishedRate),
            'publishedDate' => $publishedDate->toDateString(),
            'factorId' => $factor?->id,
            'factorCode' => $factor?->code,
            'factorValue' => $factor?->factor,
            'calculatedRate' => $automatic,
            'updatedAt' => $now,
        ];

        if (!$rate) {
            $rate = ExchangeRate::create($attributes + [
                'applicableDate' => $applicableDate->toDateString(),
                'effectiveRate' => $automatic,
                'source' => ExchangeRate::SOURCE_AUTOMATIC,
                'createdAt' => $now,
            ]);

            return $rate;
        }

        // El valor manual previamente capturado sigue prevaleciendo.
        $attributes['effectiveRate'] = $rate->manualRate ?? $automatic;
        $attributes['source'] = $rate->manualRate !== null
            ? ExchangeRate::SOURCE_MANUAL
            : ExchangeRate::SOURCE_AUTOMATIC;

        $rate->fill($attributes)->save();

        return $rate;
    }

    /**
     * Captura o corrección manual. Sólo para el día en curso y el día hábil
     * siguiente; exige motivo, que queda en el historial.
     */
    public function setManualRate(ExchangeRate $rate, string $manualRate, string $reason, ?User $actor): ExchangeRate
    {
        $this->assertEditable($rate);

        // Lo que estaba vigente antes del cambio: es contra eso que se compara
        // la corrección, aunque nunca haya existido una captura manual previa.
        $previousManual = $rate->manualRate;
        $previousEffective = $rate->effectiveRate;
        $now = Carbon::now();

        $rate->fill([
            'manualRate' => $this->round($manualRate),
            'effectiveRate' => $this->round($manualRate),
            'source' => ExchangeRate::SOURCE_MANUAL,
            'manualReason' => $reason,
            'manualSetByUserId' => $actor?->id,
            'manualSetAt' => $now,
            'updatedAt' => $now,
        ])->save();

        $this->audit->event(
            self::EVENT_MANUAL_OVERRIDE,
            'exchangerates',
            (string) $rate->uuid,
            $rate->applicableDate->toDateString(),
            [
                'previousEffectiveRate' => Decimals::rate($previousEffective),
                'previousManualRate' => Decimals::rate($previousManual),
                'manualRate' => Decimals::rate($rate->manualRate),
                'publishedRate' => Decimals::rate($rate->publishedRate),
                'reason' => $reason,
            ],
        );

        // Una corrección manual también es un cambio del tipo de cambio: quien
        // está en la lista lo usa para operar y no puede quedarse con el valor
        // que se envió por la mañana.
        $this->notifier->notify($rate);

        return $rate;
    }

    /**
     * Alta manual de una fecha sin publicación previa. El valor capturado
     * prevalecerá sobre la publicación que llegue después.
     */
    public function createManual(Carbon $applicableDate, string $manualRate, string $reason, ?User $actor): ExchangeRate
    {
        $applicableDate = $applicableDate->copy()->startOfDay();
        $this->assertDateEditable($applicableDate);

        if (ExchangeRate::query()->where('applicableDate', $applicableDate->toDateString())->exists()) {
            throw ValidationException::withMessages([
                'applicableDate' => ['Ya existe un tipo de cambio para esa fecha; use la actualización.'],
            ]);
        }

        $now = Carbon::now();

        $rate = ExchangeRate::create([
            'applicableDate' => $applicableDate->toDateString(),
            'manualRate' => $this->round($manualRate),
            'effectiveRate' => $this->round($manualRate),
            'source' => ExchangeRate::SOURCE_MANUAL,
            'manualReason' => $reason,
            'manualSetByUserId' => $actor?->id,
            'manualSetAt' => $now,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);

        $this->notifier->notify($rate);

        return $rate;
    }

    /**
     * Normaliza el histórico después de retirar el cálculo publicación × factor.
     *
     * Quita las capturas manuales y deja como vigente la publicación de Banxico
     * del registro. Los registros creados a mano, sin publicación detrás, no
     * tienen a qué volver: se listan aparte y sólo se marcan como eliminados si
     * se pide explícitamente.
     *
     * @return array{cleared: int, normalized: int, orphans: array<int, string>, orphansDropped: int}
     */
    public function clearManualOverrides(bool $dropOrphans = false, bool $dryRun = false): array
    {
        $result = ['cleared' => 0, 'normalized' => 0, 'orphans' => [], 'orphansDropped' => 0];
        $now = Carbon::now();

        $rates = ExchangeRate::query()->orderBy('applicableDate')->get();

        foreach ($rates as $rate) {
            $isManual = $rate->manualRate !== null;

            // Sin publicación no hay tipo de cambio al cual regresar.
            if ($rate->publishedRate === null) {
                if (!$isManual) {
                    continue;
                }

                $result['orphans'][] = $rate->applicableDate->toDateString();

                if ($dropOrphans && $rate->deletedAt === null) {
                    $result['orphansDropped']++;

                    if (!$dryRun) {
                        $rate->fill(['deletedAt' => $now, 'updatedAt' => $now])->save();
                    }
                }

                continue;
            }

            $automatic = $this->round((string) $rate->publishedRate);

            $attributes = [
                'calculatedRate' => $automatic,
                'effectiveRate' => $automatic,
                'source' => ExchangeRate::SOURCE_AUTOMATIC,
                'updatedAt' => $now,
            ];

            if ($isManual) {
                $attributes += [
                    'manualRate' => null,
                    'manualReason' => null,
                    'manualSetByUserId' => null,
                    'manualSetAt' => null,
                ];
            }

            $rate->fill($attributes);

            if (!$rate->isDirty()) {
                continue;
            }

            $isManual ? $result['cleared']++ : $result['normalized']++;

            if (!$dryRun) {
                $rate->save();
            }
        }

        if (!$dryRun) {
            $this->audit->event(
                self::EVENT_MANUAL_RESET,
                'exchangerates',
                null,
                'Limpieza de capturas manuales',
                [
                    'cleared' => $result['cleared'],
                    'normalized' => $result['normalized'],
                    'orphans' => $result['orphans'],
                    'orphansDropped' => $result['orphansDropped'],
                ],
            );
        }

        return $result;
    }

    public function delete(ExchangeRate $rate): ExchangeRate
    {
        $this->assertEditable($rate);

        $rate->fill(['deletedAt' => Carbon::now(), 'updatedAt' => Carbon::now()])->save();

        return $rate;
    }

    /** Tipo de cambio vigente del día en curso. */
    public function current(): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->active()
            ->where('applicableDate', Carbon::today()->toDateString())
            ->first();
    }

    public function isEditable(ExchangeRate $rate): bool
    {
        return $this->isDateEditable($rate->applicableDate);
    }

    public function isDateEditable(Carbon $date): bool
    {
        $today = Carbon::today();
        $editable = [];

        if (config('exchange.manual_edit.today', true)) {
            $editable[] = $today->toDateString();
        }

        if (config('exchange.manual_edit.next_business_day', true)) {
            $editable[] = $this->businessDays->nextBusinessDay($today)->toDateString();
        }

        return in_array($date->copy()->startOfDay()->toDateString(), $editable, true);
    }

    private function assertEditable(ExchangeRate $rate): void
    {
        $this->assertDateEditable($rate->applicableDate);
    }

    private function assertDateEditable(Carbon $date): void
    {
        if (!$this->isDateEditable($date)) {
            throw ValidationException::withMessages([
                'applicableDate' => ['La edición manual sólo está habilitada para el día actual y el día hábil siguiente.'],
            ]);
        }
    }

    private function round(string $value): string
    {
        $scale = (int) config('exchange.scale', 4);

        return number_format((float) $value, $scale, '.', '');
    }
}
