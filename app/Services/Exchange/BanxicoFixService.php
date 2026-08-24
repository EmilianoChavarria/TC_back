<?php

namespace App\Services\Exchange;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente de la SIE API de Banxico para el tipo de cambio FIX.
 *
 * La serie se publica cada día hábil y aplica al día hábil siguiente, que es
 * justamente cómo la consume el proceso diario.
 */
class BanxicoFixService
{
    /**
     * Publicaciones dentro del rango, ordenadas de la más antigua a la más reciente.
     *
     * @return array<int, array{date: Carbon, rate: string}>
     */
    public function publicationsBetween(Carbon $from, Carbon $to): array
    {
        $token = (string) config('exchange.banxico.token');

        if ($token === '') {
            throw new RuntimeException('Falta BANXICO_TOKEN: no es posible consultar la publicación de Banxico.');
        }

        $url = sprintf(
            '%s/series/%s/datos/%s/%s',
            rtrim((string) config('exchange.banxico.base_url'), '/'),
            (string) config('exchange.banxico.series'),
            $from->toDateString(),
            $to->toDateString()
        );

        $response = Http::withHeaders(['Bmx-Token' => $token])
            ->acceptJson()
            ->timeout((int) config('exchange.banxico.timeout', 15))
            ->retry(2, 500, throw: false)
            ->get($url);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Banxico respondió '.$response->status().' al consultar la serie del tipo de cambio FIX.'
            );
        }

        $rows = (array) data_get($response->json(), 'bmx.series.0.datos', []);
        $publications = [];

        foreach ($rows as $row) {
            $rate = $this->normalizeRate((string) ($row['dato'] ?? ''));
            $date = $this->parseDate((string) ($row['fecha'] ?? ''));

            // Banxico devuelve "N/E" en los días sin publicación.
            if ($rate === null || $date === null) {
                continue;
            }

            $publications[] = ['date' => $date, 'rate' => $rate];
        }

        usort($publications, fn (array $a, array $b) => $a['date']->timestamp <=> $b['date']->timestamp);

        Log::info('[Banxico] publicaciones obtenidas', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total' => count($publications),
        ]);

        return $publications;
    }

    /** Última publicación disponible hasta la fecha indicada. */
    public function latestPublicationUpTo(Carbon $date, ?int $lookbackDays = null): ?array
    {
        $lookbackDays = $lookbackDays ?? (int) config('exchange.banxico.lookback_days', 7);
        $publications = $this->publicationsBetween($date->copy()->subDays(max(1, $lookbackDays)), $date);

        return $publications === [] ? null : end($publications);
    }

    private function normalizeRate(string $value): ?string
    {
        $value = trim($value);

        // Banxico entrega punto decimal, pero se cubre la variante con coma:
        // si hay coma y punto, la coma separa miles; si sólo hay coma, es el
        // separador decimal. Confundirlos convertiría 18,9012 en 189012.
        if (str_contains($value, ',')) {
            $value = str_contains($value, '.')
                ? str_replace(',', '', $value)
                : str_replace(',', '.', $value);
        }

        return is_numeric($value) ? $value : null;
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            // La SIE API entrega dd/mm/yyyy.
            return Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
