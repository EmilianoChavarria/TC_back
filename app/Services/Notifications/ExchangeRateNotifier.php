<?php

namespace App\Services\Notifications;

use App\Mail\ExchangeRateUpdatedMail;
use App\Models\ExchangeRate;
use App\Services\Audit\AuditRecorder;
use App\Services\EmailSenderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Avisa a la lista de correos cuando queda establecido el tipo de cambio de
 * una fecha.
 *
 * Cubre los dos caminos por los que el valor puede cambiar: el proceso diario
 * de Banxico y la captura o corrección manual desde el panel. Una corrección
 * manual también es un cambio del tipo de cambio, y quien está en la lista lo
 * usa para operar.
 */
class ExchangeRateNotifier
{
    public const EVENT_SENT = 'exchangeRate.notified';

    public function __construct(
        private readonly NotificationRecipientService $recipients,
        private readonly EmailSenderService $mailer,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * Envía si procede. Devuelve false cuando no había nada que avisar.
     */
    public function notify(ExchangeRate $rate): bool
    {
        if ($rate->effectiveRate === null) {
            return false;
        }

        // ⚠️ Las fechas pasadas NO se avisan.
        //
        // `sync` consulta una ventana de días hacia atrás para recuperar
        // publicaciones caídas (BANXICO_LOOKBACK_DAYS). Sin este corte, la
        // primera corrida tras una caída mandaría una semana de correos de
        // golpe, con valores que ya no le sirven a nadie.
        if ($rate->applicableDate->lt(Carbon::today())) {
            return false;
        }

        // ⚠️ Idempotencia. `exchange-rate:sync` corre DOS veces al día y
        // reprocesa la misma fecha con el mismo valor; sin esto, el aviso
        // sale duplicado todas las tardes.
        //
        // Se compara el VALOR, no un booleano: así una corrección manual
        // posterior sí vuelve a avisar, que es cuando el aviso más importa.
        if ($this->alreadySent($rate)) {
            return false;
        }

        $isCorrection = $rate->notifiedAt !== null;

        $emails = $this->recipients->notifiable()->pluck('email')->all();

        if ($emails === []) {
            // No es un error: la lista puede estar vacía a propósito. Pero se
            // deja constancia, porque «no llegó el correo» con la lista vacía
            // parece un fallo del envío y no lo es.
            Log::info('[ExchangeRate] sin destinatarios configurados; no se envía aviso', [
                'applicableDate' => $rate->applicableDate->toDateString(),
            ]);

            return false;
        }

        // Copia oculta: es un aviso masivo y los buzones son de terceros que
        // no tienen por qué ver la lista completa de los demás.
        $sent = $this->mailer->sendTo(
            new ExchangeRateUpdatedMail($rate, $isCorrection),
            [$emails[0]],
            [],
            array_slice($emails, 1),
        );

        if (!$sent) {
            // ⚠️ NO se marca como notificado si el envío falló: así el
            // siguiente pase lo reintenta. Marcarlo aquí dejaría la fecha
            // silenciada para siempre por un fallo pasajero del correo.
            Log::warning('[ExchangeRate] el aviso no se envió; revise el modo de correo y los logs', [
                'applicableDate' => $rate->applicableDate->toDateString(),
                'recipients' => count($emails),
            ]);

            return false;
        }

        $rate->forceFill([
            'notifiedRate' => $rate->effectiveRate,
            'notifiedAt' => Carbon::now(),
        ])->save();

        $this->audit->event(
            self::EVENT_SENT,
            'exchangerates',
            $rate->id,
            $isCorrection ? 'Corrección de tipo de cambio notificada' : 'Tipo de cambio notificado',
            [
                'applicableDate' => $rate->applicableDate->toDateString(),
                'rate' => (string) $rate->effectiveRate,
                'recipients' => count($emails),
                'correction' => $isCorrection,
            ],
        );

        return true;
    }

    /**
     * Comparación numérica y no de cadenas: «16.946000» y «16.9460» son el
     * mismo valor, y compararlas como texto reenviaría el aviso cada vez que
     * cambie la escala con la que se guardó.
     */
    private function alreadySent(ExchangeRate $rate): bool
    {
        if ($rate->notifiedAt === null || $rate->notifiedRate === null) {
            return false;
        }

        return bccomp((string) $rate->notifiedRate, (string) $rate->effectiveRate, 6) === 0;
    }
}
