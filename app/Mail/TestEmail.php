<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestEmail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    public function build(): self
    {
        return $this->subject(__('emails.test_subject'))
            ->view('emails.test');
    }
}
