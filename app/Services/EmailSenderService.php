<?php

namespace App\Services;

use App\Models\EmailConfig;
use App\Services\Audit\AuditRecorder;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Punto único de salida de correo.
 *
 * Modos (tabla emailconfig):
 *  - normal:   se envía al destinatario real.
 *  - override: todo se redirige a overrideEmail (útil en QA/staging).
 *  - disabled: no se envía nada.
 */
class EmailSenderService
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    public function config(): ?EmailConfig
    {
        return EmailConfig::query()->orderBy('id')->first();
    }

    public function mode(): string
    {
        return (string) ($this->config()?->emailMode ?? EmailConfig::MODE_NORMAL);
    }

    public function send(Mailable $mailable, string $recipient, array $cc = [], array $bcc = []): bool
    {
        return $this->sendTo($mailable, [$recipient], $cc, $bcc);
    }

    /**
     * @param string[] $to
     * @param string[] $cc
     * @param string[] $bcc
     */
    public function sendTo(Mailable $mailable, array $to, array $cc = [], array $bcc = []): bool
    {
        $config = $this->config();
        $mode = (string) ($config?->emailMode ?? EmailConfig::MODE_NORMAL);

        $to = $this->clean($to);
        $cc = $this->clean($cc);
        $bcc = $this->clean($bcc);

        $context = [
            'class' => $mailable::class,
            'mode' => $mode,
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
        ];

        if ($mode === EmailConfig::MODE_DISABLED) {
            Log::info('[EmailSender] envío omitido: correo deshabilitado', $context);

            return false;
        }

        if ($to === []) {
            Log::warning('[EmailSender] envío omitido: sin destinatarios', $context);

            return false;
        }

        if ($mode === EmailConfig::MODE_OVERRIDE) {
            $override = (string) ($config?->overrideEmail ?? '');

            if ($override === '') {
                Log::warning('[EmailSender] modo override sin overrideEmail configurado; no se envía', $context);

                return false;
            }

            if (method_exists($mailable, 'applyOverrideNotice')) {
                $mailable->applyOverrideNotice(implode(', ', array_merge($to, $cc, $bcc)));
            }

            $to = [$override];
            $cc = [];
            $bcc = [];
            $context['redirectedTo'] = $override;
        }

        if ($mode === EmailConfig::MODE_NORMAL) {
            $bcc = $this->withAlwaysBcc($to, $cc, $bcc);
            $context['bcc'] = $bcc;
        }

        try {
            $mailer = Mail::to($to);

            if ($cc !== []) {
                $mailer->cc($cc);
            }

            if ($bcc !== []) {
                $mailer->bcc($bcc);
            }

            $mailer->send($mailable);
            Log::info('[EmailSender] correo enviado', $context);

            $this->audit->event(
                'email.sent',
                'emails',
                null,
                $mailable->subject ?: class_basename($mailable),
                ['to' => $to, 'cc' => $cc, 'bcc' => $bcc, 'mode' => $mode],
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('[EmailSender] fallo al enviar correo', $context + ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Copia oculta permanente de `mail.always_bcc`.
     *
     * Se agrega sólo en modo normal: en override el correo ya se redirige
     * entero a una dirección de pruebas, y colar la copia ahí delataría los
     * destinatarios reales de producción.
     *
     * Se descarta la dirección que ya viene como destinatario o en copia, para
     * no mandarle el mismo correo dos veces.
     *
     * @param string[] $to
     * @param string[] $cc
     * @param string[] $bcc
     * @return string[]
     */
    private function withAlwaysBcc(array $to, array $cc, array $bcc): array
    {
        $always = $this->clean((array) config('mail.always_bcc', []));

        if ($always === []) {
            return $bcc;
        }

        $existing = array_map('mb_strtolower', array_merge($to, $cc, $bcc));

        $extra = array_filter(
            $always,
            static fn (string $address) => !in_array(mb_strtolower($address), $existing, true),
        );

        return array_values(array_merge($bcc, $extra));
    }

    /**
     * @param string[] $addresses
     * @return string[]
     */
    private function clean(array $addresses): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($address) => trim((string) $address), $addresses),
            static fn (string $address) => $address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) !== false
        )));
    }
}
