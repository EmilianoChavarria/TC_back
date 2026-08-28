<?php

namespace App\Services\Exchange;

use App\Models\ExchangeRate;
use App\Support\Decimals;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Consulta pública del tipo de cambio.
 *
 * ⚠️ Este servicio es la ÚNICA fuente de la vista sin sesión, y su trabajo es
 * decidir qué NO sale. Nada de lo que revele que hay un portal detrás: ni
 * `uuid`, ni `source`, ni si el valor fue capturado a mano, ni el motivo, ni
 * quién lo hizo, ni fechas de publicación de la fuente, ni el estado del
 * proceso automático. Sólo la fecha, el valor, su variación y la serie del
 * periodo.
 *
 * Al agregar un campo aquí, la pregunta no es «¿sirve?», sino «¿qué le dice a
 * quien no debería saber nada de nosotros?».
 */
class PublicExchangeRateService
{
    /** Ventana por omisión y límites admitidos para la serie. */
    public const DEFAULT_DAYS = 30;
    public const MIN_DAYS = 7;
    public const MAX_DAYS = 90;

    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $days = self::DEFAULT_DAYS): array
    {
        $days = max(self::MIN_DAYS, min(self::MAX_DAYS, $days));
        $today = Carbon::today();

        // El vigente es el último publicado hasta hoy, no el de la fecha exacta:
        // en fin de semana o feriado la consulta pública tiene que responder algo.
        $current = ExchangeRate::query()
            ->active()
            ->whereNotNull('effectiveRate')
            ->whereDate('applicableDate', '<=', $today->toDateString())
            ->orderByDesc('applicableDate')
            ->first();

        if (!$current) {
            return [
                'available' => false,
                'currency' => $this->currency(),
                'window' => ['days' => $days],
            ];
        }

        $from = $current->applicableDate->copy()->subDays($days);

        /** @var Collection<int, ExchangeRate> $series */
        $series = ExchangeRate::query()
            ->active()
            ->whereNotNull('effectiveRate')
            ->whereDate('applicableDate', '>=', $from->toDateString())
            ->whereDate('applicableDate', '<=', $current->applicableDate->toDateString())
            ->orderBy('applicableDate')
            ->get();

        $values = $series->map(fn (ExchangeRate $rate) => (float) $rate->effectiveRate)->all();

        return [
            'available' => true,
            'currency' => $this->currency(),
            'date' => $current->applicableDate->toDateString(),
            'rate' => Decimals::rate($current->effectiveRate),

            // Factor informativo del rango en el que cae el tipo de cambio del
            // día. Es el único dato de configuración que sale: se pidió
            // explícitamente para la consulta pública.
            'factor' => Decimals::factor($current->factorValue),

            'change' => $this->change($current, $this->previousOf($current)),
            'window' => [
                'days' => $days,
                'from' => $series->first()?->applicableDate->toDateString(),
                'to' => $current->applicableDate->toDateString(),
                'min' => $values === [] ? null : $this->rate(min($values)),
                'max' => $values === [] ? null : $this->rate(max($values)),
            ],
            'points' => $series
                ->map(fn (ExchangeRate $rate) => [
                    'date' => $rate->applicableDate->toDateString(),
                    'rate' => Decimals::rate($rate->effectiveRate),
                ])
                ->values()
                ->all(),

            // El historial va del más reciente al más antiguo: es el orden en
            // el que se lee una tabla de fechas.
            'history' => $this->history($series),
        ];
    }

    /**
     * Variación de cada fecha contra la anterior de la propia serie.
     *
     * @param Collection<int, ExchangeRate> $series
     * @return array<int, array<string, mixed>>
     */
    private function history(Collection $series): array
    {
        $rows = [];
        $previous = null;

        foreach ($series as $rate) {
            $rows[] = [
                'date' => $rate->applicableDate->toDateString(),
                'rate' => Decimals::rate($rate->effectiveRate),
                'change' => $this->change($rate, $previous),
            ];

            $previous = $rate;
        }

        return array_reverse($rows);
    }

    /** Registro vigente inmediatamente anterior al indicado. */
    private function previousOf(ExchangeRate $rate): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->active()
            ->whereNotNull('effectiveRate')
            ->whereDate('applicableDate', '<', $rate->applicableDate->toDateString())
            ->orderByDesc('applicableDate')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function change(ExchangeRate $rate, ?ExchangeRate $previous): ?array
    {
        if (!$previous || $rate->effectiveRate === null || $previous->effectiveRate === null) {
            return null;
        }

        $scale = (int) config('exchange.scale', 4);
        $difference = round((float) $rate->effectiveRate - (float) $previous->effectiveRate, $scale);
        $before = (float) $previous->effectiveRate;

        return [
            'amount' => $this->rate($difference),
            'percentage' => $before == 0.0 ? null : round(($difference / $before) * 100, 4),
            'direction' => $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'flat'),
        ];
    }

    private function rate(float $value): string
    {
        return number_format($value, (int) config('exchange.scale', 4), '.', '');
    }

    private function currency(): string
    {
        return 'MXN/USD';
    }
}
