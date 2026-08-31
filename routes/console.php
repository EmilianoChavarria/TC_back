<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| Requiere un cron del sistema que invoque cada minuto:
|   php /ruta/al/proyecto/artisan schedule:run
*/

/*
 * Banxico publica el FIX cada día hábil alrededor del mediodía y esa publicación
 * aplica al día hábil siguiente. Se corre por la tarde y se repite más tarde por
 * si la publicación se retrasó; la ventana de días hacia atrás
 * (BANXICO_LOOKBACK_DAYS) recupera sola cualquier día caído.
 */
Schedule::command('exchange-rate:sync --days=30 --trigger=scheduled')
    ->weekdays()
    ->at('13:30')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('exchange-rate:sync --days=30 --trigger=scheduled')
    ->weekdays()
    ->at('18:00')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->onOneServer();

/*
 * Recordatorio de captura de días feriados del año siguiente. El propio comando
 * decide si toca enviarlo: sólo dentro de la ventana configurada, sólo si el
 * año siguiente no tiene ni un día capturado y una vez al día.
 */
Schedule::command('holidays:remind')
    ->dailyAt((string) config('holidays.reminder.hour', '07:00'))
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->onOneServer();
