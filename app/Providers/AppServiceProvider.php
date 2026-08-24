<?php

namespace App\Providers;

use App\Listeners\LogMailSent;
use App\Services\Audit\AuditContext;
use App\Services\Exchange\BusinessDayService;
use App\Services\Exchange\HolidayService;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Una instancia por petición: acumula los cambios y los escribe al final.
        $this->app->scoped(AuditContext::class);

        // El calendario de feriados se consulta una sola vez por petición: los
        // listados evalúan día hábil fila por fila.
        $this->app->scoped(HolidayService::class);
        $this->app->scoped(BusinessDayService::class);
    }

    public function boot(): void
    {
        // Laravel no trae transporte SendGrid de fábrica: se registra el puente
        // de Symfony (symfony/sendgrid-mailer) como mailer "sendgrid".
        Mail::extend('sendgrid', function (array $config) {
            return (new SendgridTransportFactory())->create(
                new Dsn('sendgrid+api', 'default', $config['key'] ?? '')
            );
        });

        Event::listen(MessageSent::class, LogMailSent::class);
    }
}
