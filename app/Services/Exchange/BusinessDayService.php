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
