<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

class LogMailSent
{
    public function handle(MessageSent $event): void
    {
        $addresses = static fn (?array $list) => array_map(
            static fn ($address) => $address->getAddress(),
            $list ?? []
        );

        Log::info('[Mail] mensaje entregado al transporte', [
            'mailer' => config('mail.default'),
            'subject' => $event->message->getSubject(),
            'to' => $addresses($event->message->getTo()),
            'cc' => $addresses($event->message->getCc()),
            'bcc' => $addresses($event->message->getBcc()),
        ]);
    }
}
