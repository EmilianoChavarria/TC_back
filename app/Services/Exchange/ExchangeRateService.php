<?php

namespace App\Services\Exchange;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Cálculo y captura del tipo de cambio.
 *
 * Regla del negocio: la publicación de Banxico de un día hábil aplica al día
 * hábil siguiente. Sobre esa publicación se busca el factor cuyo rango la
 * contiene y el resultado es publicación × factor.
 *
 * La captura manual nunca pisa el valor calculado: se guarda aparte y prevalece
 * como vigente, incluso si el cálculo automático llega después.
 */
class ExchangeRateService
{
    /** Eventos de la línea de tiempo que produce este servicio. */
    public const EVENT_SYNC = 'exchangeRate.sync';
    public const EVENT_SYNC_FAILED = 'exchangeRate.syncFailed';
    public const EVENT_MANUAL_OVERRIDE = 'exchangeRate.manualOverride';

    public function __construct(
        private readonly BanxicoFixService $banxico,
        private readonly BusinessDayService $businessDays,
        private readonly ExchangeRateFactorService $factors,
        private readonly AuditRecorder $audit,
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
     * Guarda una publicación y su cálculo en la fecha aplicable que le toca
     * (el día hábil siguiente al de la publicación).
     */
    public function applyPublication(Carbon $publishedDate, string $publishedRate): ExchangeRate
    {
        $applicableDate = $this->businessDays->nextBusinessDay($publishedDate);
        $factor = $this->factors->resolveFor($publishedRate);
        $now = Carbon::now();

        if (!$factor) {
            // Sin factor no se inventa uno: se conserva la publicación tal cual
            // y queda registrado para que el administrador cubra el rango.
            Log::warning('[ExchangeRate] ninguna clave de factor cubre la publicación', [
                'publishedDate' => $publishedDate->toDateString(),
                'publishedRate' => $publishedRate,
            ]);
        }

        $calculated = $factor
            ? $this->multiply($publishedRate, (string) $factor->factor)
            : $this->round($publishedRate);

        $rate = ExchangeRate::query()->where('applicableDate', $applicableDate->toDateString())->first();

        $attributes = [
            'publishedRate' => $publishedRate,
            'publishedDate' => $publishedDate->toDateString(),
            'factorId' => $factor?->id,
            'factorCode' => $factor?->code,
            'factorValue' => $factor?->factor,
            'calculatedRate' => $calculated,
            'updatedAt' => $now,
        ];

        if (!$rate) {
            $rate = ExchangeRate::create($attributes + [
                'applicableDate' => $applicableDate->toDateString(),
                'effectiveRate' => $calculated,
                'source' => ExchangeRate::SOURCE_AUTOMATIC,
                'createdAt' => $now,
            ]);

            return $rate;
        }

        // El valor manual previamente capturado sigue prevaleciendo.
        $attributes['effectiveRate'] = $rate->manualRate ?? $calculated;
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

        $previousManual = $rate->manualRate;
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
                'previousManualRate' => $previousManual,
                'manualRate' => (string) $rate->manualRate,
                'calculatedRate' => (string) $rate->calculatedRate,
                'reason' => $reason,
            ],
        );

        return $rate;
    }

    /**
     * Alta manual de una fecha sin cálculo previo. El valor capturado
     * prevalecerá sobre cualquier cálculo posterior.
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

        return ExchangeRate::create([
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

    private function multiply(string $rate, string $factor): string
    {
        $scale = (int) config('exchange.scale', 4);
        // bcmul evita el error de coma flotante antes de redondear.
        $product = bcmul($rate, $factor, $scale + 6);

        return $this->round($product);
    }

    private function round(string $value): string
    {
        $scale = (int) config('exchange.scale', 4);

        return number_format((float) $value, $scale, '.', '');
    }
}
