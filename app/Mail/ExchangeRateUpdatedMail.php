<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use App\Models\EmailConfig;
use App\Models\ExchangeRate;
use App\Support\Decimals;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso del tipo de cambio del día a la lista de notificaciones.
 */
class ExchangeRateUpdatedMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

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
}
