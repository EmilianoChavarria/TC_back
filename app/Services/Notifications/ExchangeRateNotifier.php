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
     * Avisa del último tipo de cambio establecido.
     *
     * Es la entrada del envío programado, que corre a su propia hora y no
     * dentro de la sincronización. Toma la fecha aplicable más lejana que ya
     * tenga valor —normalmente el día hábil siguiente, publicado al mediodía—
     * y deja que `notify()` decida si toca mandarlo.
     *
     * Devuelve false cuando no había nada que avisar: sin registros, o el
     * valor ya se avisó.
     */
    public function notifyLatest(): bool
    {
        $rate = ExchangeRate::query()
            ->active()
            ->whereNotNull('effectiveRate')
            ->where('applicableDate', '>=', Carbon::today()->toDateString())
            ->orderByDesc('applicableDate')
            ->first();

        if ($rate === null) {
            Log::info('[ExchangeRate] no hay tipo de cambio vigente que avisar');

            return false;
        }

        return $this->notify($rate);
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
        $bcc = array_merge(array_slice($emails, 1), $this->fixedBcc($emails));

        $sent = $this->mailer->sendTo(
            new ExchangeRateUpdatedMail($this->featured($rate), $isCorrection, $rate),
            [$emails[0]],
            [],
            $bcc,
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
     * Qué registro va en la tarjeta y en el asunto del aviso.
     *
     * Lo que dispara el correo es el registro nuevo —normalmente el del día
     * hábil siguiente, que es el que acaba de llegar—, pero lo que la lista
     * necesita leer primero es **el tipo de cambio con el que se opera hoy**.
     * El de mañana no se pierde: sale como una fila más del historial, que
     * llega hasta esa fecha.
     *
     * Excepción: una corrección manual se anuncia sobre la fecha corregida.
     * Si alguien corrige el valor de mañana, el correo tiene que hablar de
     * mañana; destacar hoy dejaría la corrección enterrada en la tabla.
     */
    private function featured(ExchangeRate $rate): ExchangeRate
    {
        if ($rate->isManual()) {
            return $rate;
        }

        $today = ExchangeRate::query()
            ->active()
            ->where('applicableDate', Carbon::today()->toDateString())
            ->first();

        // Sin registro de hoy (primera corrida, o un hueco sin arrastre) se
        // anuncia el que llegó: es preferible a no mandar nada.
        return $today?->effectiveRate !== null ? $today : $rate;
    }

    /**
     * Copia oculta fija de `mail.exchange_rate_bcc`: el respaldo del operador
     * para poder decir si un aviso salió o se perdió en el camino.
     *
     * Va sólo en este correo y no en `EmailSenderService`, porque el resto de
     * los correos son personales —alta de cuenta, contraseña temporal— y
     * copiarlos sería leer el buzón de alguien.
     *
     * Se descarta la dirección que ya está en la lista de destinatarios, para
     * no mandarle el mismo correo dos veces.
     *
     * @param  string[]  $recipients
     * @return string[]
     */
    private function fixedBcc(array $recipients): array
    {
        $fixed = (array) config('mail.exchange_rate_bcc', []);
        $existing = array_map('mb_strtolower', $recipients);

        return array_values(array_filter(
            array_map(static fn ($address) => trim((string) $address), $fixed),
            static fn (string $address) => $address !== ''
                && !in_array(mb_strtolower($address), $existing, true),
        ));
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
