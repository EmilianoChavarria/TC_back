<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use App\Models\EmailConfig;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateFactor;
use App\Support\Decimals;
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

    /** Ventana hacia atrás de la tabla comparativa. */
    private const COMPARISON_DAYS_BACK = 30;

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
     * Tabla comparativa: hace 30 días, hoy y mañana.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $comparison;

    /**
     * Catálogo vigente de factores con su rango y su equivalencia.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $factors;

    public function __construct(ExchangeRate $rate, bool $isCorrection = false)
    {
        $this->supportEmail = (string) (EmailConfig::query()->orderBy('id')->first()?->emailSupport ?? '');
        $this->portalUrl = (string) config('security.frontend_url');

        $this->applicableDate = $rate->applicableDate->translatedFormat('l j \d\e F \d\e Y');
        $this->effectiveRate = (string) Decimals::rate($rate->effectiveRate);
        $this->factorValue = Decimals::factor($rate->factorValue);
        $this->factorCode = $rate->factorCode;
        $this->isManual = $rate->source === ExchangeRate::SOURCE_MANUAL;
        $this->manualReason = $rate->manualReason;
        $this->isCorrection = $isCorrection;

        $this->comparison = $this->buildComparison($rate);
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
     * Filas de la tabla comparativa.
     *
     * Las fechas se anclan al día de HOY y no a la fecha aplicable del aviso:
     * quien lee el correo lo hace hoy, y «mañana» tiene que significar mañana.
     * Cuando la fecha aplicable no cae en esa ventana —el día hábil siguiente
     * a un viernes o a un feriado— se agrega como fila extra para que el valor
     * que anuncia el correo siempre aparezca en la tabla.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildComparison(ExchangeRate $rate): array
    {
        $today = Carbon::today();
        $applicable = $rate->applicableDate->copy()->startOfDay();

        /** @var array<string, string> $targets fecha => etiqueta */
        $targets = [
            $today->copy()->subDays(self::COMPARISON_DAYS_BACK)->toDateString() => __('emails.exchange_rate_row_past', ['days' => self::COMPARISON_DAYS_BACK]),
            $today->toDateString() => __('emails.exchange_rate_row_today'),
            $today->copy()->addDay()->toDateString() => __('emails.exchange_rate_row_tomorrow'),
        ];

        if (!array_key_exists($applicable->toDateString(), $targets)) {
            $targets[$applicable->toDateString()] = __('emails.exchange_rate_row_applicable');
        }

        ksort($targets);

        // Una sola consulta para todas las fechas: la tabla es decorativa y no
        // justifica una consulta por fila.
        $rates = ExchangeRate::query()
            ->active()
            ->whereIn('applicableDate', array_keys($targets))
            ->get()
            ->keyBy(fn (ExchangeRate $row) => $row->applicableDate->toDateString());

        $rows = [];

        foreach ($targets as $date => $label) {
            $found = $rates->get($date);
            $carbon = Carbon::parse($date);

            $rows[] = [
                'label' => $label,
                'date' => $carbon->format('d/m/Y'),
                'weekday' => $carbon->translatedFormat('D'),
                'rate' => $found?->effectiveRate !== null ? Decimals::rate($found->effectiveRate) : null,
                'factorValue' => $found ? Decimals::factor($found->factorValue) : null,
                'factorCode' => $found?->factorCode,
                'note' => $found ? $this->sourceNote($found) : null,
                // La fila del valor que anuncia este correo va resaltada.
                'highlight' => $date === $applicable->toDateString(),
            ];
        }

        return $rows;
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
