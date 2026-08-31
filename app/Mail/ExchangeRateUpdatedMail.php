<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use App\Models\EmailConfig;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateFactor;
use App\Support\Decimals;
use App\Support\FrontendUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Aviso del tipo de cambio del día a la lista de notificaciones.
 */
class ExchangeRateUpdatedMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    /** Ventana hacia atrás del historial que se anexa al aviso. */
    private const HISTORY_DAYS_BACK = 30;

    public string $supportEmail;
    public string $portalUrl;

    public string $applicableDate;
    public string $effectiveRate;
    public ?string $factorValue;
    public ?int $factorCode;
    public bool $isManual;
    public ?string $manualReason;

    /** true cuando este aviso corrige un valor que ya se había enviado. */
    public bool $isCorrection;

    /**
     * Historial de los últimos 30 días, del más reciente al más antiguo.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $history;

    /**
     * Máximo, mínimo y número de registros del periodo que cubre el historial.
     *
     * @var array<string, mixed>
     */
    public array $summary;

    /**
     * Catálogo vigente de factores con su rango y su equivalencia.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $factors;

    public function __construct(ExchangeRate $rate, bool $isCorrection = false)
    {
        $this->supportEmail = (string) (EmailConfig::query()->orderBy('id')->first()?->emailSupport ?? '');
        $this->portalUrl = FrontendUrl::to('tipo-de-cambio');

        $this->applicableDate = $rate->applicableDate->translatedFormat('l j \d\e F \d\e Y');
        $this->effectiveRate = (string) Decimals::rate($rate->effectiveRate);
        $this->factorValue = Decimals::factor($rate->factorValue);
        $this->factorCode = $rate->factorCode;
        $this->isManual = $rate->source === ExchangeRate::SOURCE_MANUAL;
        $this->manualReason = $rate->manualReason;
        $this->isCorrection = $isCorrection;

        $this->history = $this->buildHistory($rate);
        $this->summary = $this->buildSummary($this->history);
        $this->factors = $this->buildFactors($rate);
    }

    public function build(): self
    {
        // El asunto distingue el aviso normal de la corrección: quien ya
        // apuntó el valor de la mañana necesita ver de un vistazo que este
        // correo lo reemplaza, sin abrirlo.
        $key = $this->isCorrection
            ? 'emails.exchange_rate_corrected_subject'
            : 'emails.exchange_rate_updated_subject';

        return $this->subject(__($key, ['date' => $this->applicableDate, 'rate' => $this->effectiveRate]))
            ->view('emails.exchange_rate_updated');
    }

    /**
     * Historial de los 30 días anteriores a la fecha que anuncia el aviso,
     * incluida esa fecha. Sólo se listan los días con registro: un fin de
     * semana sin arrastre no tiene valor y una fila vacía por cada uno dejaría
     * la tabla ilegible.
     *
     * La ventana se ancla a la fecha aplicable y no a hoy, para que el valor
     * que anuncia el correo sea siempre la primera fila.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildHistory(ExchangeRate $rate): array
    {
        $applicable = $rate->applicableDate->copy()->startOfDay();
        $from = $applicable->copy()->subDays(self::HISTORY_DAYS_BACK);
        $today = Carbon::today();

        // Una sola consulta para toda la tabla: el historial es informativo y
        // no justifica una consulta por fila.
        $rates = ExchangeRate::query()
            ->active()
            ->whereBetween('applicableDate', [$from->toDateString(), $applicable->toDateString()])
            ->orderBy('applicableDate')
            ->get();

        $rows = [];
        $previous = null;

        // Se recorre en orden ascendente porque la variación de cada día se
        // calcula contra el registro anterior; la tabla se invierte al final.
        foreach ($rates as $row) {
            $value = $row->effectiveRate !== null ? (float) $row->effectiveRate : null;
            $delta = ($value !== null && $previous !== null) ? $value - $previous : null;

            $date = $row->applicableDate->copy()->startOfDay();

            $rows[] = [
                'date' => $date->format('d/m/Y'),
                'weekday' => ucfirst($date->translatedFormat('D')),
                'rate' => Decimals::rate($row->effectiveRate),
                'rateValue' => $value,
                'delta' => $delta !== null ? Decimals::rate(abs($delta)) : null,
                'deltaSign' => $delta === null ? null : ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat')),
                'factorValue' => Decimals::factor($row->factorValue),
                'factorCode' => $row->factorCode,
                'note' => $this->sourceNote($row),
                'tag' => $this->dateTag($date, $today, $applicable),
                // La fila del valor que anuncia este correo va resaltada.
                'highlight' => $date->equalTo($applicable),
            ];

            if ($value !== null) {
                $previous = $value;
            }
        }

        return array_reverse($rows);
    }

    /** Etiqueta corta que ubica la fila sin tener que leer la fecha. */
    private function dateTag(Carbon $date, Carbon $today, Carbon $applicable): ?string
    {
        if ($date->equalTo($today)) {
            return __('emails.exchange_rate_row_today');
        }

        if ($date->equalTo($today->copy()->addDay())) {
            return __('emails.exchange_rate_row_tomorrow');
        }

        if ($date->equalTo($applicable)) {
            return __('emails.exchange_rate_row_applicable');
        }

        return null;
    }

    /**
     * Máximo y mínimo del periodo. Da contexto de si el valor del aviso es
     * alto o bajo respecto al mes sin tener que leer las veinte filas.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    private function buildSummary(array $history): array
    {
        $values = array_values(array_filter(
            array_column($history, 'rateValue'),
            fn ($value) => $value !== null
        ));

        if ($values === []) {
            return ['days' => self::HISTORY_DAYS_BACK, 'records' => 0, 'max' => null, 'min' => null];
        }

        return [
            'days' => self::HISTORY_DAYS_BACK,
            'records' => count($values),
            'max' => Decimals::rate(max($values)),
            'min' => Decimals::rate(min($values)),
        ];
    }

    /** De dónde sale el valor de la fila; null cuando es la publicación normal. */
    private function sourceNote(ExchangeRate $rate): ?string
    {
        if ($rate->isManual()) {
            return __('emails.exchange_rate_source_manual');
        }

        if ($rate->isCarried()) {
            return __('emails.exchange_rate_source_carried', [
                'date' => $rate->carriedFromDate?->format('d/m/Y') ?? '—',
            ]);
        }

        return null;
    }

    /**
     * Catálogo vigente de factores. El rango es [rangeFrom, rangeTo): límite
     * inferior inclusivo y superior exclusivo, igual que `scopeContaining`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildFactors(ExchangeRate $rate): array
    {
        return ExchangeRateFactor::query()
            ->active()
            ->orderBy('rangeFrom')
            ->get()
            ->map(fn (ExchangeRateFactor $factor) => [
                'code' => $factor->code,
                'rangeFrom' => Decimals::rate($factor->rangeFrom),
                'rangeTo' => Decimals::rate($factor->rangeTo),
                'factor' => Decimals::factor($factor->factor),
                // Se resalta el rango en el que cayó la publicación de este aviso.
                'highlight' => $rate->factorCode !== null && $factor->code === $rate->factorCode,
            ])
            ->all();
    }
}
