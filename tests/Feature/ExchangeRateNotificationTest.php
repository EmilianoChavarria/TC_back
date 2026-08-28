<?php

namespace Tests\Feature;

use App\Mail\ExchangeRateUpdatedMail;
use App\Models\EmailConfig;
use App\Models\ExchangeRate;
use App\Models\NotificationRecipient;
use App\Services\Notifications\ExchangeRateNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Aviso del tipo de cambio a la lista de destinatarios.
 *
 * Lo que se prueba aquí no es que el correo salga —eso es fácil— sino que NO
 * salga cuando no debe: el proceso está programado dos veces al día y consulta
 * una ventana de días hacia atrás, así que el camino natural es que se
 * duplique.
 */
class ExchangeRateNotificationTest extends TestCase
{
    use RefreshDatabase;

    private ExchangeRateNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Sin fila de configuración el servicio asume modo normal, pero se
        // crea explícita para que la prueba no dependa de ese supuesto.
        EmailConfig::create([
            'emailSupport' => 'soporte@example.com',
            'emailMode' => EmailConfig::MODE_NORMAL,
        ]);

        $this->notifier = app(ExchangeRateNotifier::class);
    }

    public function test_avisa_a_los_destinatarios_activos(): void
    {
        $this->recipient('pagos@empresa.mx');
        $this->recipient('tesoreria@empresa.mx');

        $this->assertTrue($this->notifier->notify($this->rate()));

        Mail::assertSent(ExchangeRateUpdatedMail::class, 1);
    }

    public function test_no_avisa_dos_veces_el_mismo_valor(): void
    {
        $this->recipient('pagos@empresa.mx');
        $rate = $this->rate();

        $this->assertTrue($this->notifier->notify($rate));

        // ⚠️ `exchange-rate:sync` corre a las 13:30 y otra vez a las 18:00,
        // reprocesando la misma fecha con el mismo valor. Sin el corte, esta
        // segunda pasada manda el aviso por duplicado todas las tardes.
        $this->assertFalse($this->notifier->notify($rate->fresh()));

        Mail::assertSent(ExchangeRateUpdatedMail::class, 1);
    }

    public function test_una_correccion_manual_si_vuelve_a_avisar(): void
    {
        $this->recipient('pagos@empresa.mx');
        $rate = $this->rate();

        $this->notifier->notify($rate);

        // Alguien corrige el valor después del envío de la mañana. Es justo
        // cuando el aviso más importa: la lista está operando con el valor
        // anterior.
        $rate->forceFill([
            'effectiveRate' => '17.100000',
            'source' => ExchangeRate::SOURCE_MANUAL,
            'manualReason' => 'Corrección de captura',
        ])->save();

        $this->assertTrue($this->notifier->notify($rate->fresh()));

        Mail::assertSent(ExchangeRateUpdatedMail::class, 2);
    }

    public function test_la_correccion_se_marca_como_tal_en_el_correo(): void
    {
        $this->recipient('pagos@empresa.mx');
        $rate = $this->rate();

        $this->notifier->notify($rate);
        $rate->forceFill(['effectiveRate' => '17.100000'])->save();
        $this->notifier->notify($rate->fresh());

        // Quien ya apuntó el valor de la mañana necesita distinguir el segundo
        // correo del primero sin abrirlo.
        Mail::assertSent(
            ExchangeRateUpdatedMail::class,
            fn (ExchangeRateUpdatedMail $mail) => $mail->isCorrection === true,
        );
    }

    public function test_una_diferencia_solo_de_escala_no_reenvia(): void
    {
        $this->recipient('pagos@empresa.mx');
        $rate = $this->rate();

        $this->notifier->notify($rate);

        // «16.946000» y «16.9460» son el mismo número. Comparando como texto,
        // cualquier cambio de escala reenviaría el aviso.
        $rate->forceFill(['effectiveRate' => '16.9460'])->save();

        $this->assertFalse($this->notifier->notify($rate->fresh()));
        Mail::assertSent(ExchangeRateUpdatedMail::class, 1);
    }

    public function test_no_avisa_fechas_ya_pasadas(): void
    {
        $this->recipient('pagos@empresa.mx');

        // La ventana de recuperación de Banxico reprocesa días caídos. Sin el
        // corte, la primera corrida tras una caída manda una semana de correos
        // de golpe, con valores que ya no le sirven a nadie.
        $viejo = $this->rate(Carbon::today()->subDays(3));

        $this->assertFalse($this->notifier->notify($viejo));
        Mail::assertNothingSent();
    }

    public function test_un_destinatario_pausado_no_recibe(): void
    {
        $this->recipient('pagos@empresa.mx', active: false);

        $this->assertFalse($this->notifier->notify($this->rate()));
        Mail::assertNothingSent();
    }

    public function test_un_destinatario_eliminado_no_recibe(): void
    {
        $this->recipient('pagos@empresa.mx')->forceFill(['deletedAt' => Carbon::now()])->save();

        $this->assertFalse($this->notifier->notify($this->rate()));
        Mail::assertNothingSent();
    }

    public function test_con_la_lista_vacia_no_se_marca_como_notificado(): void
    {
        $rate = $this->rate();

        $this->assertFalse($this->notifier->notify($rate));

        // ⚠️ Si se marcara, agregar el primer destinatario más tarde ese mismo
        // día dejaría la fecha silenciada para siempre.
        $this->assertNull($rate->fresh()->notifiedAt);
    }

    public function test_si_el_correo_esta_deshabilitado_no_se_marca_como_notificado(): void
    {
        EmailConfig::query()->update(['emailMode' => EmailConfig::MODE_DISABLED]);
        $this->recipient('pagos@empresa.mx');

        $rate = $this->rate();

        $this->assertFalse($this->notifier->notify($rate));

        // Marcarlo aquí dejaría el aviso perdido: al reactivar el correo, esa
        // fecha ya no volvería a intentarse.
        $this->assertNull($rate->fresh()->notifiedAt);
    }

    private function recipient(string $email, bool $active = true): NotificationRecipient
    {
        return NotificationRecipient::create([
            'email' => $email,
            'name' => 'Prueba',
            'isActive' => $active,
            'createdAt' => Carbon::now(),
            'updatedAt' => Carbon::now(),
        ]);
    }

    private function rate(?Carbon $date = null): ExchangeRate
    {
        return ExchangeRate::create([
            'applicableDate' => ($date ?? Carbon::today())->toDateString(),
            'publishedRate' => '16.946000',
            'publishedDate' => Carbon::today()->subDay()->toDateString(),
            'calculatedRate' => '16.946000',
            'effectiveRate' => '16.946000',
            'factorCode' => 4,
            'factorValue' => '0.775000',
            'source' => ExchangeRate::SOURCE_AUTOMATIC,
            'createdAt' => Carbon::now(),
            'updatedAt' => Carbon::now(),
        ]);
    }
}
