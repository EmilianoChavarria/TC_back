<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use App\Models\EmailConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserRegisteredMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    public string $supportEmail;
    public string $loginUrl;
    private string $mailLocale;

    public function __construct(
        public string $fullName,
        public string $email,
        public string $password,
        string $locale = 'es',
    ) {
        $this->mailLocale = in_array(strtolower(trim($locale)), ['es', 'en'], true)
            ? strtolower(trim($locale))
            : (string) config('app.fallback_locale', 'es');

        $this->supportEmail = (string) (EmailConfig::query()->orderBy('id')->first()?->emailSupport ?? '');
        $this->loginUrl = (string) config('security.frontend_url');
    }

    public function build(): self
    {
        app()->setLocale($this->mailLocale);

        return $this->subject(__('emails.welcome_subject'))
            ->view('emails.user_registered');
    }
}
