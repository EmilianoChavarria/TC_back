<?php

namespace App\Services\Exchange;

use App\Models\ExchangeRate;
use App\Models\ExchangeRateSyncRun;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Notifications\ExchangeRateNotifier;
use App\Support\Decimals;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

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
 *
 * En los días feriados capturados en su módulo no hay publicación aplicable:
 * el vigente es el del día hábil anterior, que la sincronización arrastra a
 * esa fecha (`SOURCE_CARRIED`).
 */
class ExchangeRateService
{
    /** Eventos de la línea de tiempo que produce este servicio. */
    public const EVENT_SYNC = 'exchangeRate.sync';
    public const EVENT_SYNC_FAILED = 'exchangeRate.syncFailed';
    public const EVENT_MANUAL_OVERRIDE = 'exchangeRate.manualOverride';
    public const EVENT_MANUAL_RESET = 'exchangeRate.manualReset';
    public const EVENT_HOLIDAY_CARRY = 'exchangeRate.holidayCarryOver';

    public function __construct(
        private readonly BanxicoFixService $banxico,
        private readonly BusinessDayService $businessDays,
        private readonly ExchangeRateFactorService $factors,
        private readonly AuditRecorder $audit,
        private readonly ExchangeRateNotifier $notifier,
        private readonly ExchangeRateSyncLogger $syncLog,
    ) {
    }

    /**
     * Trae las publicaciones recientes y actualiza las fechas aplicables.
     *
     * Cada corrida deja una fila en `exchangeratesyncruns`, termine bien o mal.
     * Se registra aquí y no en el comando porque el panel dispara la misma
     * sincronización por HTTP: hacerlo en cada punto de entrada dejaría fuera
     * al que se olvide.
     *
     * @param string $trigger Origen de la corrida (ExchangeRateSyncRun::TRIGGER_*)
     * @return array{processed: int, dates: array<int, string>, carried: array<int, array{date: string, from: string}>}
     */
    public function sync(
        ?Carbon $referenceDate = null,
        ?int $lookbackDays = null,
        string $trigger = ExchangeRateSyncRun::TRIGGER_CONSOLE,
        ?User $actor = null,
    ): array {
        $referenceDate = ($referenceDate ?? Carbon::today())->copy()->startOfDay();
        $lookbackDays = $lookbackDays ?? (int) config('exchange.banxico.lookback_days', 7);

        $run = $this->syncLog->start($trigger, $referenceDate, $lookbackDays, $actor);

        try {
            $result = $this->runSync($referenceDate, $lookbackDays);
        } catch (Throwable $e) {
            $this->syncLog->failed($run, $e);

            throw $e;
        }

        $this->syncLog->succeeded($run, $result);

        return $result;
    }

    /**
     * @return array{processed: int, dates: array<int, string>, carried: array<int, array{date: string, from: string}>}
     */
    private function runSync(Carbon $referenceDate, int $lookbackDays): array
    {
        $publications = $this->banxico->publicationsBetween(
            $referenceDate->copy()->subDays(max(1, $lookbackDays)),
            $referenceDate
        );

        $dates = [];

        foreach ($publications as $publication) {
            $rate = $this->applyPublication($publication['date'], $publication['rate']);
            $dates[] = $rate->applicableDate->toDateString();
        }

        // Los feriados no tienen publicación aplicable: se les arrastra el
        // tipo de cambio del día hábil anterior. Se hace DESPUÉS de aplicar
        // las publicaciones para que el valor arrastrado sea el ya vigente y
        // no el que había antes de esta corrida.
        $carried = $this->carryOverHolidays(
            $referenceDate->copy()->subDays(max(1, $lookbackDays)),
            $this->businessDays->nextBusinessDay($referenceDate)
        );

        // ⚠️ Aquí NO se avisa por correo. La sincronización corre al mediodía,
        // cuando publica Banxico, y el aviso sale más tarde por su propio
        // comando (`exchange-rate:notify`): son dos horarios distintos porque
        // responden a cosas distintas —la publicación de un tercero y la
        // rutina de quien lee el correo—. Atarlos obligaría a mover uno cada
        // vez que se mueve el otro.
        //
        // La captura manual sí avisa en el momento: una corrección no puede
        // esperar a la hora del envío programado.

        // Deja constancia de la corrida: es lo que alimenta el estado del
        // proceso automático en el tablero.
        $this->audit->event(
            self::EVENT_SYNC,
            'exchangerates',
            null,
            'Sincronización con Banxico',
            ['processed' => count($dates), 'dates' => $dates, 'carried' => $carried],
        );

        return ['processed' => count($dates), 'dates' => $dates, 'carried' => $carried];
    }

    /**
     * Arrastra el tipo de cambio a los días feriados del rango.
     *
     * En un día feriado nadie opera, pero el portal se sigue consultando y la
     * vista pública tiene que responder algo: sin esto, la fecha simplemente no
     * existe y el tipo de cambio del día aparece vacío.
     *
     * El rango llega hasta el siguiente día hábil para cubrir el feriado que
     * viene ANTES de que llegue: al cerrar el día previo ya queda el registro,
     * y no depende de que la sincronización corra durante el propio feriado.
     *
     * ⚠️ Nunca pisa un registro existente. Si alguien capturó el valor a mano
     * para esa fecha, esa captura manda; y una fecha ya arrastrada no se vuelve
     * a tocar aunque la corrida se repita.
     *
     * @return array<int, array{date: string, from: string}>
     */
    public function carryOverHolidays(Carbon $from, Carbon $to): array
    {
        if (!config('exchange.holiday_carry_over', true)) {
            return [];
        }

        $carried = [];

        foreach ($this->businessDays->holidaysBetween($from, $to) as $holiday) {
            $rate = $this->carryOverHoliday($holiday);

            if ($rate) {
                $carried[] = [
                    'date' => $rate->applicableDate->toDateString(),
                    'from' => $rate->carriedFromDate?->toDateString(),
                ];
            }
        }

        return $carried;
    }

    /** Devuelve el registro creado, o null si no procedía crearlo. */
    private function carryOverHoliday(Carbon $holiday): ?ExchangeRate
    {
        $date = $holiday->copy()->startOfDay();

        // Sin `active()` a propósito: la fecha es única en la tabla, así que un
        // registro eliminado también cuenta. Reactivarlo es una decisión del
        // usuario, no del proceso.
        $existing = ExchangeRate::query()
            ->whereDate('applicableDate', $date->toDateString())
            ->first();

        if ($existing && !$this->shouldReplaceWithCarryOver($existing)) {
            return null;
        }

        $previous = ExchangeRate::query()
            ->active()
            ->whereNotNull('effectiveRate')
            ->where('applicableDate', '<', $date->toDateString())
            ->orderByDesc('applicableDate')
            ->first();

        if (!$previous) {
            // Pasa en la puesta en marcha, con el histórico todavía vacío. No
            // es un error: no hay nada que arrastrar.
            Log::warning('[ExchangeRate] feriado sin tipo de cambio anterior que arrastrar', [
                'applicableDate' => $date->toDateString(),
            ]);

            return null;
        }

        $now = Carbon::now();

        // Se copian la publicación y el factor del día de origen: el registro
        // tiene que poder explicarse solo cuando se ve en el histórico.
        $attributes = [
            'publishedRate' => $previous->publishedRate,
            'publishedDate' => $previous->publishedDate?->toDateString(),
            'carriedFromDate' => $previous->applicableDate->toDateString(),
            'factorId' => $previous->factorId,
            'factorCode' => $previous->factorCode,
            'factorValue' => $previous->factorValue,
            'calculatedRate' => $previous->effectiveRate,
            'effectiveRate' => $previous->effectiveRate,
            'source' => ExchangeRate::SOURCE_CARRIED,
            'updatedAt' => $now,
        ];

        if ($existing) {
            // El feriado ya arrastrado se reevalúa: si se preparó por la mañana
            // con el valor del viernes y por la tarde llegó la publicación del
            // lunes, el feriado tiene que conservar la del lunes.
            $unchanged = $existing->isCarried()
                && $existing->carriedFromDate?->toDateString() === $previous->applicableDate->toDateString()
                && bccomp((string) $existing->effectiveRate, (string) $previous->effectiveRate, 6) === 0;

            if ($unchanged) {
                return null;
            }

            Log::info('[ExchangeRate] feriado: se arrastra el TC del día hábil anterior', [
                'applicableDate' => $date->toDateString(),
                'carriedFrom' => $previous->applicableDate->toDateString(),
                'previousSource' => $existing->source,
            ]);

            $existing->fill($attributes)->save();

            return $existing;
        }

        return ExchangeRate::create($attributes + [
            'applicableDate' => $date->toDateString(),
            'createdAt' => $now,
        ]);
    }

    /**
     * Una fecha que YA tenía tipo de cambio y después se marcó como feriado.
     *
     * Sólo se reemplaza de hoy en adelante: el histórico es lo que
     * efectivamente se usó para operar ese día y no se reescribe, y una captura
     * manual manda siempre —quien la hizo sabía que era feriado—. También entra
     * aquí el feriado ya arrastrado, para reevaluar de qué día toma el valor.
     */
    private function shouldReplaceWithCarryOver(ExchangeRate $rate): bool
    {
        if ($rate->manualRate !== null || $rate->deletedAt !== null) {
            return false;
        }

        return $rate->applicableDate->gte(Carbon::today());
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
            // Llegó publicación para esta fecha: ya no es un valor arrastrado,
            // aunque lo haya sido mientras el día figuraba como feriado.
            'carriedFromDate' => null,
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
            // El arrastre de un feriado copia la publicación del día de origen;
            // normalizarlo contra ella lo convertiría en un automático que
            // aparenta una publicación propia que nunca existió.
            if ($rate->isCarried()) {
                continue;
            }

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
