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
 * aplica al día hábil siguiente. Se corre a esa hora y se repite por la tarde
 * por si la publicación se retrasó; la ventana de días hacia atrás
 * (BANXICO_LOOKBACK_DAYS) recupera sola cualquier día caído.
 */
Schedule::command('exchange-rate:sync --days=30 --trigger=scheduled')
    ->weekdays()
    ->at('12:00')
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
 * El aviso a la lista va aparte de la sincronización y dos horas después: el
 * horario del correo lo manda la rutina de quien lo lee, no el de la
 * publicación de Banxico.
 *
 * Se repite tras la corrida de recuperación de las 18:00, para el día en que la
 * publicación llegó tarde y a las 14:00 no había nada nuevo que avisar. No
 * duplica el correo: el notificador compara el valor ya enviado.
 */
Schedule::command('exchange-rate:notify')
    ->weekdays()
    ->at('14:00')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('exchange-rate:notify')
    ->weekdays()
    ->at('18:15')
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
