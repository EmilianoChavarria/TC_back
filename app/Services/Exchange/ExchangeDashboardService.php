<?php

namespace App\Services\Exchange;

use App\Models\AuditLog;
use App\Models\ExchangeRate;
use App\Support\Decimals;
use Illuminate\Support\Carbon;

/**
 * Datos del tablero de tipo de cambio: tarjeta del día, día hábil siguiente,
 * indicadores y serie de fluctuación.
 */
class ExchangeDashboardService
{
    public function __construct(private readonly BusinessDayService $businessDays)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $days = 30): array
    {
        $today = Carbon::today();
        $nextBusinessDay = $this->businessDays->nextBusinessDay($today);

        return [
            'currency' => 'MXN/USD',
            'today' => $this->todayCard($today),
            'nextBusinessDay' => $this->nextBusinessDayCard($nextBusinessDay),
            'manualEntries' => $this->manualEntries($days),
            'automaticProcess' => $this->automaticProcess(),
            'fluctuation' => $this->fluctuation($days),
        ];
    }

    /** Tarjeta principal: valor vigente de hoy y variación contra el registro anterior. */
    private function todayCard(Carbon $today): array
    {
        $rate = ExchangeRate::query()
            ->active()
            ->where('applicableDate', $today->toDateString())
            ->first();

        if (!$rate) {
            return [
                'available' => false,
                'date' => $today->toDateString(),
                'dateLabel' => $this->longDate($today),
            ];
        }

        $previous = ExchangeRate::query()
            ->active()
            ->where('applicableDate', '<', $rate->applicableDate->toDateString())
            ->whereNotNull('effectiveRate')
            ->orderByDesc('applicableDate')
            ->first();

        return [
            'available' => true,
            'date' => $rate->applicableDate->toDateString(),
            'dateLabel' => $this->longDate($rate->applicableDate),
            'effectiveRate' => $this->decimal($rate->effectiveRate),
            'publishedRate' => $this->decimal($rate->publishedRate),
            'publishedDate' => $rate->publishedDate?->toDateString(),
            'factorCode' => $rate->factorCode,
            'factorValue' => Decimals::factor($rate->factorValue),
            'factorApplied' => $rate->factorValue !== null,
            'source' => $rate->source,
            'sourceLabel' => $rate->isManual() ? 'Captura manual' : 'Publicación Banxico',
            'change' => $this->change($rate, $previous),
        ];
    }

    /** Variación contra el registro vigente anterior. */
    private function change(ExchangeRate $rate, ?ExchangeRate $previous): ?array
    {
        if (!$previous || $rate->effectiveRate === null) {
            return null;
        }

        $scale = (int) config('exchange.scale', 4);
        $current = (float) $rate->effectiveRate;
        $before = (float) $previous->effectiveRate;
        $difference = round($current - $before, $scale);

        return [
            'amount' => number_format($difference, $scale, '.', ''),
            'percentage' => $before == 0.0 ? null : round(($difference / $before) * 100, 4),
            'direction' => $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'flat'),
            'comparedTo' => $previous->applicableDate->toDateString(),
            'comparedToRate' => $this->decimal($previous->effectiveRate),
        ];
    }

    /** Tarjeta del día hábil siguiente, con la marca de cuándo se generó el cálculo. */
    private function nextBusinessDayCard(Carbon $date): array
    {
        $rate = ExchangeRate::query()
            ->active()
            ->where('applicableDate', $date->toDateString())
            ->first();

        if (!$rate) {
            return [
                'available' => false,
                'date' => $date->toDateString(),
                'dateLabel' => $this->longDate($date),
            ];
        }

        return [
            'available' => true,
            'date' => $rate->applicableDate->toDateString(),
            'dateLabel' => $this->longDate($rate->applicableDate),
            'effectiveRate' => $this->decimal($rate->effectiveRate),
            'publishedRate' => $this->decimal($rate->publishedRate),
            'publishedDate' => $rate->publishedDate?->toDateString(),
            'factorCode' => $rate->factorCode,
            'factorValue' => Decimals::factor($rate->factorValue),
            'factorApplied' => $rate->factorValue !== null,
            'source' => $rate->source,
            'sourceLabel' => $rate->isManual() ? 'Captura manual' : 'Publicación Banxico',
            'calculatedAt' => $rate->updatedAt?->toIso8601String(),
            'isEditable' => true,
        ];
    }

    /** Cuántos registros del periodo llevan captura manual. */
    private function manualEntries(int $days): array
    {
        $from = Carbon::today()->subDays($days);

        return [
            'days' => $days,
            'total' => ExchangeRate::query()
                ->active()
                ->whereNotNull('manualRate')
                ->where('applicableDate', '>=', $from->toDateString())
                ->count(),
        ];
    }

    /** Última corrida del proceso automático, según la línea de tiempo. */
    private function automaticProcess(): array
    {
        $last = AuditLog::query()
            ->whereIn('event', [ExchangeRateService::EVENT_SYNC, ExchangeRateService::EVENT_SYNC_FAILED])
            ->orderByDesc('createdAt')
            ->orderByDesc('id')
            ->first();

        if (!$last) {
            return [
                'status' => 'pending',
                'statusLabel' => 'Sin ejecutar',
                'ranAt' => null,
                'ranToday' => false,
            ];
        }

        $failed = $last->event === ExchangeRateService::EVENT_SYNC_FAILED;

        return [
            'status' => $failed ? 'failed' : 'executed',
            'statusLabel' => $failed ? 'Falló' : 'Ejecutado',
            'ranAt' => $last->createdAt?->toIso8601String(),
            'ranToday' => $last->createdAt?->isToday() ?? false,
            'processed' => $last->newValues['processed'] ?? null,
            'error' => $failed ? ($last->newValues['error'] ?? null) : null,
        ];
    }

    /**
     * Serie para la gráfica: valor vigente contra la publicación de Banxico.
     * Incluye el día hábil siguiente cuando ya se sincronizó.
     *
     * @return array<string, mixed>
     */
    public function fluctuation(int $days = 30): array
    {
        $today = Carbon::today();
        $from = $today->copy()->subDays($days);
        $to = $this->businessDays->nextBusinessDay($today);

        $rates = ExchangeRate::query()
            ->active()
            ->whereBetween('applicableDate', [$from->toDateString(), $to->toDateString()])
            ->orderBy('applicableDate')
            ->get();

        $points = $rates->map(fn (ExchangeRate $rate) => [
            'date' => $rate->applicableDate->toDateString(),
            'effectiveRate' => $this->decimal($rate->effectiveRate),
            'publishedRate' => $this->decimal($rate->publishedRate),
            'publishedDate' => $rate->publishedDate?->toDateString(),
            'source' => $rate->source,
        ])->all();

        $values = array_values(array_filter(array_map(
            fn (array $point) => $point['effectiveRate'] !== null ? (float) $point['effectiveRate'] : null,
            $points
        ), fn ($value) => $value !== null));

        return [
            'days' => $days,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'series' => [
                'effectiveRate' => 'Tipo de cambio vigente',
                'publishedRate' => 'Publicación Banxico',
            ],
            'points' => $points,
            'min' => $values === [] ? null : number_format(min($values), (int) config('exchange.scale', 4), '.', ''),
            'max' => $values === [] ? null : number_format(max($values), (int) config('exchange.scale', 4), '.', ''),
        ];
    }

    private function decimal(mixed $value): ?string
    {
        return Decimals::rate($value);
    }

    /** «miércoles 19 de agosto de 2026» */
    private function longDate(Carbon $date): string
    {
        return $date->copy()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
    }
}
