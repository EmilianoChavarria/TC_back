<?php

namespace App\Services\Exchange;

use Illuminate\Support\Carbon;

/**
 * Días hábiles del calendario bancario: descarta sábados, domingos y los días
 * feriados capturados en el módulo correspondiente.
 */
class BusinessDayService
{
    public function __construct(private readonly HolidayService $holidays)
    {
    }

    public function isBusinessDay(Carbon $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return !in_array($date->toDateString(), $this->holidayDates(), true);
    }

    public function nextBusinessDay(Carbon $date): Carbon
    {
        $next = $date->copy()->addDay();

        while (!$this->isBusinessDay($next)) {
            $next->addDay();
        }

        return $next->startOfDay();
    }

    public function previousBusinessDay(Carbon $date): Carbon
    {
        $previous = $date->copy()->subDay();

        while (!$this->isBusinessDay($previous)) {
            $previous->subDay();
        }

        return $previous->startOfDay();
    }

    /**
     * Día feriado capturado en el módulo correspondiente.
     *
     * No es lo contrario de `isBusinessDay`: un sábado tampoco es hábil, pero
     * no es feriado. La distinción importa para el arrastre del tipo de cambio,
     * que sólo aplica a los feriados de entre semana.
     */
    public function isHoliday(Carbon $date): bool
    {
        return in_array($date->toDateString(), $this->holidayDates(), true);
    }

    /**
     * Días feriados de entre semana dentro del rango, de la fecha más antigua
     * a la más reciente.
     *
     * @return array<int, Carbon>
     */
    public function holidaysBetween(Carbon $from, Carbon $to): array
    {
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $holidays = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            if (!$cursor->isWeekend() && $this->isHoliday($cursor)) {
                $holidays[] = $cursor->copy();
            }

            $cursor->addDay();
        }

        return $holidays;
    }

    /** El propio día si es hábil; si no, el siguiente hábil. */
    public function currentOrNextBusinessDay(Carbon $date): Carbon
    {
        $current = $date->copy()->startOfDay();

        while (!$this->isBusinessDay($current)) {
            $current->addDay();
        }

        return $current;
    }

    /** @return array<int, string> */
    private function holidayDates(): array
    {
        return $this->holidays->dates();
    }
}
