<?php

namespace App\Console\Commands;

use App\Mail\HolidayCaptureReminderMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\EmailSenderService;
use App\Services\Exchange\HolidayService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * A partir de la fecha configurada (15 de noviembre por omisión) recuerda por
 * correo a todos los usuarios activos que falta capturar el calendario de días
 * feriados del año siguiente. Deja de enviarse en cuanto se captura el primero.
 */
class SendHolidayReminderCommand extends Command
{
    protected $signature = 'holidays:remind
                            {--force : Envía aunque la fecha aún no entre en la ventana o ya se haya enviado hoy}';

    protected $description = 'Recuerda capturar los días feriados del año siguiente';

    public const EVENT = 'holidayReminder.sent';

    public function handle(
        HolidayService $holidays,
        EmailSenderService $mailer,
        AuditRecorder $audit,
    ): int {
        if (!config('holidays.reminder.enabled', true) && !$this->option('force')) {
            $this->info('El recordatorio está deshabilitado por configuración.');

            return self::SUCCESS;
        }

        $status = $holidays->status();
        $pendingYear = $status['pendingYear'];

        if ($pendingYear === null) {
            $this->info("El calendario de {$status['nextYear']} ya está capturado; no hay nada que recordar.");

            return self::SUCCESS;
        }

        if (!$status['reminderActive'] && !$this->option('force')) {
            $this->info("El recordatorio inicia el {$status['reminderStartsOn']}.");

            return self::SUCCESS;
        }

        if ($this->alreadySentToday() && !$this->option('force')) {
            $this->info('El recordatorio de hoy ya se envió.');

            return self::SUCCESS;
        }

        $recipients = User::query()
            ->whereNull('deletedAt')
            ->where('isActive', true)
            ->pluck('email')
            ->all();

        if ($recipients === []) {
            $this->warn('No hay usuarios activos a quienes enviar el recordatorio.');

            return self::SUCCESS;
        }

        // Copia oculta: es un aviso masivo, nadie necesita ver la lista completa.
        $sent = $mailer->sendTo(new HolidayCaptureReminderMail($pendingYear), [$recipients[0]], [], array_slice($recipients, 1));

        if (!$sent) {
            $this->warn('El recordatorio no se envió; revise el modo de correo configurado y los logs.');

            return self::SUCCESS;
        }

        $audit->event(
            self::EVENT,
            'holidays',
            null,
            "Correo automático de captura de feriados {$pendingYear}",
            ['year' => $pendingYear, 'recipients' => count($recipients)],
        );

        $this->info("Recordatorio de {$pendingYear} enviado a ".count($recipients).' usuarios.');

        return self::SUCCESS;
    }

    private function alreadySentToday(): bool
    {
        return AuditLog::query()
            ->where('event', self::EVENT)
            ->where('createdAt', '>=', Carbon::today())
            ->exists();
    }
}
