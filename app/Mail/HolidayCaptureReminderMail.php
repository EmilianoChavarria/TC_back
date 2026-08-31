<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use App\Models\EmailConfig;
use App\Support\FrontendUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Recordatorio de captura del calendario de días feriados del año siguiente.
 */
class HolidayCaptureReminderMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    public string $supportEmail;
    public string $portalUrl;

    public function __construct(public int $year)
    {
        $this->supportEmail = (string) (EmailConfig::query()->orderBy('id')->first()?->emailSupport ?? '');
        $this->portalUrl = FrontendUrl::to('dias-feriados');
    }

    public function build(): self
    {
        return $this->subject(__('emails.holiday_reminder_subject', ['year' => $this->year]))
            ->view('emails.holiday_reminder');
    }
}
