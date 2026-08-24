<?php

namespace App\Services\Exchange;

use App\Models\ExchangeRateFactor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExchangeRateFactorService
{
    /**
     * Factor vigente cuyo rango contiene el valor publicado.
     * Límite inferior inclusivo, superior exclusivo.
     */
    public function resolveFor(string|float $rate): ?ExchangeRateFactor
    {
        return ExchangeRateFactor::query()->containing($rate)->orderBy('rangeFrom')->first();
    }

    public function create(array $data): ExchangeRateFactor
    {
        $this->assertValidRange($data['rangeFrom'], $data['rangeTo']);
        $this->assertNoOverlap($data['rangeFrom'], $data['rangeTo']);

        $now = Carbon::now();

        return ExchangeRateFactor::create([
            'code' => $this->nextCode(),
            'rangeFrom' => $data['rangeFrom'],
            'rangeTo' => $data['rangeTo'],
            'factor' => $data['factor'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
    }

    public function update(ExchangeRateFactor $factor, array $data): ExchangeRateFactor
    {
        $rangeFrom = $data['rangeFrom'] ?? $factor->rangeFrom;
        $rangeTo = $data['rangeTo'] ?? $factor->rangeTo;

        $this->assertValidRange($rangeFrom, $rangeTo);
        $this->assertNoOverlap($rangeFrom, $rangeTo, $factor->id);

        $factor->fill([
            'rangeFrom' => $rangeFrom,
            'rangeTo' => $rangeTo,
            'factor' => $data['factor'] ?? $factor->factor,
            'updatedAt' => Carbon::now(),
        ])->save();

        return $factor;
    }

    /** Baja lógica: el factor deja de aplicar pero el historial lo conserva. */
    public function delete(ExchangeRateFactor $factor): ExchangeRateFactor
    {
        if ($factor->isDeleted()) {
            return $factor;
        }

        $factor->fill([
            'deletedAt' => Carbon::now(),
            'updatedAt' => Carbon::now(),
        ])->save();

        return $factor;
    }

    public function restore(ExchangeRateFactor $factor): ExchangeRateFactor
    {
        if (!$factor->isDeleted()) {
            return $factor;
        }

        $this->assertNoOverlap($factor->rangeFrom, $factor->rangeTo, $factor->id);

        $factor->fill([
            'deletedAt' => null,
            'updatedAt' => Carbon::now(),
        ])->save();

        return $factor;
    }

    /**
     * Guardado por lote: los elementos con `uuid` se actualizan y el resto se
     * dan de alta. Todo ocurre en una transacción, así que un rango inválido
     * deja la tabla como estaba.
     *
     * El traslape se valida sobre el conjunto resultante — los factores que
     * quedarán vigentes tras aplicar el lote —, no elemento por elemento: así
     * un lote que reacomoda varios rangos a la vez no se rechaza por estados
     * intermedios.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function bulkSave(array $items): Collection
    {
        $existing = ExchangeRateFactor::query()->active()->get()->keyBy('uuid');
        $touched = [];
        $projected = [];

        foreach ($items as $index => $item) {
            $uuid = $item['uuid'] ?? null;

            if ($uuid !== null && !$existing->has($uuid)) {
                throw ValidationException::withMessages([
                    "factors.{$index}.uuid" => ['El factor indicado no existe o está eliminado'],
                ]);
            }

            $this->assertValidRangeAt($item['rangeFrom'], $item['rangeTo'], $index);

            if ($uuid !== null) {
                $touched[] = $uuid;
            }

            $projected[] = [
                'index' => $index,
                'uuid' => $uuid,
                'rangeFrom' => (float) $item['rangeFrom'],
                'rangeTo' => (float) $item['rangeTo'],
            ];
        }

        // Los vigentes que el lote no toca también entran a la validación.
        foreach ($existing as $uuid => $factor) {
            if (!in_array($uuid, $touched, true)) {
                $projected[] = [
                    'index' => null,
                    'uuid' => $uuid,
                    'rangeFrom' => (float) $factor->rangeFrom,
                    'rangeTo' => (float) $factor->rangeTo,
                ];
            }
        }

        $this->assertBatchWithoutOverlap($projected);

        return DB::transaction(function () use ($items, $existing) {
            $now = Carbon::now();
            $saved = new Collection();

            foreach ($items as $item) {
                $uuid = $item['uuid'] ?? null;

                if ($uuid !== null) {
                    $factor = $existing->get($uuid);
                    $factor->fill([
                        'rangeFrom' => $item['rangeFrom'],
                        'rangeTo' => $item['rangeTo'],
                        'factor' => $item['factor'],
                        'updatedAt' => $now,
                    ])->save();
                } else {
                    $factor = ExchangeRateFactor::create([
                        'code' => $this->nextCode(),
                        'rangeFrom' => $item['rangeFrom'],
                        'rangeTo' => $item['rangeTo'],
                        'factor' => $item['factor'],
                        'createdAt' => $now,
                        'updatedAt' => $now,
                    ]);
                }

                $saved->push($factor);
            }

            return $saved;
        });
    }

    private function assertValidRangeAt(string|float $from, string|float $to, int $index): void
    {
        if ((float) $to <= (float) $from) {
            throw ValidationException::withMessages([
                "factors.{$index}.rangeTo" => ['El límite superior debe ser mayor que el inferior'],
            ]);
        }
    }

    /**
     * Ningún par del conjunto resultante puede traslaparse.
     *
     * @param array<int, array{index: ?int, uuid: ?string, rangeFrom: float, rangeTo: float}> $projected
     */
    private function assertBatchWithoutOverlap(array $projected): void
    {
        usort($projected, fn (array $a, array $b) => $a['rangeFrom'] <=> $b['rangeFrom']);

        for ($i = 1; $i < count($projected); $i++) {
            $previous = $projected[$i - 1];
            $current = $projected[$i];

            if ($current['rangeFrom'] < $previous['rangeTo']) {
                $index = $current['index'] ?? $previous['index'];
                $key = $index === null ? 'factors' : "factors.{$index}.rangeFrom";

                throw ValidationException::withMessages([
                    $key => [sprintf(
                        'Los rangos %s a %s y %s a %s se traslapan',
                        $this->format($previous['rangeFrom']),
                        $this->format($previous['rangeTo']),
                        $this->format($current['rangeFrom']),
                        $this->format($current['rangeTo'])
                    )],
                ]);
            }
        }
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    private function nextCode(): int
    {
        return (int) ExchangeRateFactor::query()->max('code') + 1;
    }

    private function assertValidRange(string|float $from, string|float $to): void
    {
        if ((float) $to <= (float) $from) {
            throw ValidationException::withMessages([
                'rangeTo' => ['El límite superior debe ser mayor que el inferior'],
            ]);
        }
    }

    /** Los rangos vigentes no pueden traslaparse. */
    private function assertNoOverlap(string|float $from, string|float $to, ?int $ignoreId = null): void
    {
        $overlap = ExchangeRateFactor::query()
            ->active()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('rangeFrom', '<', $to)
            ->where('rangeTo', '>', $from)
            ->orderBy('rangeFrom')
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'rangeFrom' => [sprintf(
                    'El rango se traslapa con la clave %d (%s a %s)',
                    $overlap->code,
                    $overlap->rangeFrom,
                    $overlap->rangeTo
                )],
            ]);
        }
    }
}
