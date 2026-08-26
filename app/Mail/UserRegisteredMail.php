<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use App\Models\EmailConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Alta de usuario con sus credenciales de acceso. Todo el correo va en español. */
class UserRegisteredMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    public string $supportEmail;
    public string $loginUrl;

    public function __construct(
        public string $fullName,
        public string $email,
        public string $password,
    ) {
        $this->supportEmail = (string) (EmailConfig::query()->orderBy('id')->first()?->emailSupport ?? '');
        $this->loginUrl = (string) config('security.frontend_url');
    }

    public function build(): self
    {
        return $this->subject(__('emails.welcome_subject'))
            ->view('emails.user_registered');
    }
}
