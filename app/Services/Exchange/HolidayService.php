<?php

namespace App\Services\Exchange;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Administración de días feriados y consulta para el cálculo de días hábiles.
 */
class HolidayService
{
    /** @var array<int, string>|null Memoria por instancia; el servicio vive una petición. */
    private ?array $dates = null;

    /**
     * Fechas vigentes en formato Y-m-d.
     *
     * @return array<int, string>
     */
    public function dates(): array
    {
        if ($this->dates !== null) {
            return $this->dates;
        }

        $stored = Holiday::query()
            ->active()
            ->orderBy('holidayDate')
            ->pluck('holidayDate')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        // Los días fijos del .env se conservan como respaldo (útil antes de la
        // primera captura y en entornos sin base de datos poblada).
        $fallback = (array) config('exchange.holidays', []);

        return $this->dates = array_values(array_unique(array_merge($stored, $fallback)));
    }

    public function forget(): void
    {
        $this->dates = null;
    }

    /** @return Collection<int, Holiday> */
    public function listByYear(int $year, bool $includeDeleted = false): Collection
    {
        return Holiday::query()
            ->when(!$includeDeleted, fn ($query) => $query->active())
            ->forYear($year)
            ->orderBy('holidayDate')
            ->get();
    }

    /**
     * Datos del aviso de la pantalla: qué años están cubiertos y si falta
     * capturar el siguiente.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $today = Carbon::today();
        $currentYear = (int) $today->year;
        $nextYear = $currentYear + (int) config('holidays.years_ahead', 1);

        $counts = [];

        foreach (range($currentYear, $nextYear) as $year) {
            $counts[$year] = Holiday::query()->active()->forYear($year)->count();
        }

        $pendingYear = $counts[$nextYear] === 0 ? $nextYear : null;

        return [
            'years' => array_map(
                fn (int $year) => ['year' => $year, 'total' => $counts[$year], 'captured' => $counts[$year] > 0],
                range($currentYear, $nextYear)
            ),
            'currentYear' => $currentYear,
            'nextYear' => $nextYear,
            'pendingYear' => $pendingYear,
            'reminderActive' => $pendingYear !== null && $this->isWithinReminderWindow($today),
            'reminderStartsOn' => $this->reminderStart($currentYear)->toDateString(),
        ];
    }

    /** ¿Ya estamos en la ventana en la que se recuerda capturar el año siguiente? */
    public function isWithinReminderWindow(?Carbon $date = null): bool
    {
        $date = ($date ?? Carbon::today())->copy()->startOfDay();

        return $date->greaterThanOrEqualTo($this->reminderStart((int) $date->year));
    }

    public function reminderStart(int $year): Carbon
    {
        [$month, $day] = array_pad(explode('-', (string) config('holidays.reminder.start', '11-15')), 2, '1');

        return Carbon::create($year, (int) $month, (int) $day)->startOfDay();
    }

    // ------------------------------------------------------------------- CRUD

    public function create(array $data): Holiday
    {
        $date = $this->normalizeDate($data['holidayDate']);
        $this->assertYearAllowed($date);
        $this->assertDateAvailable($date);

        $now = Carbon::now();
        $this->forget();

        return Holiday::create([
            'holidayDate' => $date->toDateString(),
            'description' => trim((string) $data['description']),
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
    }

    public function update(Holiday $holiday, array $data): Holiday
    {
        $date = isset($data['holidayDate'])
            ? $this->normalizeDate($data['holidayDate'])
            : $holiday->holidayDate->copy();

        $this->assertYearAllowed($date);
        $this->assertDateAvailable($date, $holiday->id);
        $this->forget();

        $holiday->fill([
            'holidayDate' => $date->toDateString(),
            'description' => trim((string) ($data['description'] ?? $holiday->description)),
            'updatedAt' => Carbon::now(),
        ])->save();

        return $holiday;
    }

    public function delete(Holiday $holiday): Holiday
    {
        if (!$holiday->isDeleted()) {
            $this->forget();
            $holiday->fill(['deletedAt' => Carbon::now(), 'updatedAt' => Carbon::now()])->save();
        }

        return $holiday;
    }

    public function restore(Holiday $holiday): Holiday
    {
        if ($holiday->isDeleted()) {
            $this->assertDateAvailable($holiday->holidayDate, $holiday->id);
            $this->forget();
            $holiday->fill(['deletedAt' => null, 'updatedAt' => Carbon::now()])->save();
        }

        return $holiday;
    }

    /**
     * Captura por lote: lo habitual es dar de alta el calendario completo de un
     * año en una sola operación. Todo o nada.
     *
     * @param array<int, array<string, mixed>> $items
     * @return Collection<int, Holiday>
     */
    public function bulkSave(array $items): Collection
    {
        $seen = [];

        foreach ($items as $index => $item) {
            $date = $this->normalizeDate($item['holidayDate'])->toDateString();

            if (isset($seen[$date])) {
                throw ValidationException::withMessages([
                    "holidays.{$index}.holidayDate" => ["La fecha {$date} viene repetida en el envío"],
                ]);
            }

            $seen[$date] = $index;
        }

        return DB::transaction(function () use ($items) {
            $saved = new Collection();

            foreach ($items as $index => $item) {
                try {
                    $uuid = $item['uuid'] ?? null;

                    if ($uuid !== null) {
                        $holiday = Holiday::query()->whereUuid($uuid)->first();

                        if (!$holiday) {
                            throw ValidationException::withMessages([
                                "holidays.{$index}.uuid" => ['El día feriado indicado no existe'],
                            ]);
                        }

                        $saved->push($this->update($holiday, $item));

                        continue;
                    }

                    $saved->push($this->create($item));
                } catch (ValidationException $e) {
                    // Se reetiquetan los errores con el índice del elemento para
                    // que la pantalla marque la fila exacta.
                    throw ValidationException::withMessages(
                        collect($e->errors())
                            ->mapWithKeys(fn ($messages, $key) => [
                                str_starts_with($key, 'holidays.') ? $key : "holidays.{$index}.{$key}" => $messages,
                            ])
                            ->all()
                    );
                }
            }

            return $saved;
        });
    }

    // ---------------------------------------------------------------- soporte

    private function normalizeDate(mixed $value): Carbon
    {
        return Carbon::parse((string) $value)->startOfDay();
    }

    private function assertDateAvailable(Carbon $date, ?int $ignoreId = null): void
    {
        $exists = Holiday::query()
            ->active()
            ->whereDate('holidayDate', $date->toDateString())
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'holidayDate' => ['Ya existe un día feriado vigente para esa fecha'],
            ]);
        }
    }

    private function assertYearAllowed(Carbon $date): void
    {
        $currentYear = (int) Carbon::today()->year;
        $maxYear = $currentYear + max(1, (int) config('holidays.years_ahead', 1));

        if ((int) $date->year < $currentYear || (int) $date->year > $maxYear) {
            throw ValidationException::withMessages([
                'holidayDate' => ["Sólo se capturan días feriados entre {$currentYear} y {$maxYear}"],
            ]);
        }
    }
}
