<?php

namespace Tests\Feature;

use App\Models\NotificationRecipient;
use App\Services\Notifications\NotificationRecipientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Alta de varios correos de una vez.
 *
 * Lo que se prueba es el comportamiento con una lista REAL: pegada de un
 * correo o de una hoja de cálculo, con repetidos, con alguna dirección ya
 * dada de alta y con algún error de dedo.
 */
class NotificationRecipientBulkTest extends TestCase
{
    use RefreshDatabase;

    private NotificationRecipientService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(NotificationRecipientService::class);
    }

    public function test_agrega_cada_correo_como_un_registro(): void
    {
        $result = $this->service->createMany([
            'pagos@empresa.mx',
            'tesoreria@empresa.mx',
            'direccion@empresa.mx',
        ]);

        $this->assertCount(3, $result['created']);
        $this->assertSame(3, NotificationRecipient::query()->count());
    }

    public function test_los_correos_ya_registrados_se_reportan_y_no_se_duplican(): void
    {
        $this->service->create(['email' => 'pagos@empresa.mx']);

        // El caso normal al pegar una lista: parte ya estaba. Rechazar el lote
        // entero obligaría a depurarla a mano antes, que es justo el trabajo
        // que esta pantalla evita.
        $result = $this->service->createMany(['pagos@empresa.mx', 'nuevo@empresa.mx']);

        $this->assertCount(1, $result['created']);
        $this->assertSame(['pagos@empresa.mx'], $result['duplicates']);
        $this->assertSame(2, NotificationRecipient::query()->count());
    }

    public function test_una_direccion_mal_escrita_no_tumba_el_lote(): void
    {
        $result = $this->service->createMany([
            'pagos@empresa.mx',
            'esto-no-es-un-correo',
            'tesoreria@empresa.mx',
        ]);

        $this->assertCount(2, $result['created']);
        $this->assertSame(['esto-no-es-un-correo'], $result['invalid']);
    }

    public function test_un_repetido_dentro_del_mismo_lote_entra_una_sola_vez(): void
    {
        // Pasa al pegar dos listas seguidas. Sin esto se crearían dos
        // registros y esa persona recibiría el aviso por duplicado.
        $result = $this->service->createMany([
            'pagos@empresa.mx',
            'PAGOS@empresa.mx',
            '  pagos@empresa.mx  ',
        ]);

        $this->assertCount(1, $result['created']);
        $this->assertSame(1, NotificationRecipient::query()->count());
    }

    public function test_las_mayusculas_se_normalizan(): void
    {
        $this->service->createMany(['Pagos@Empresa.MX']);

        $this->assertSame('pagos@empresa.mx', NotificationRecipient::query()->value('email'));
    }

    public function test_una_direccion_eliminada_puede_volver_a_darse_de_alta(): void
    {
        $recipient = $this->service->create(['email' => 'pagos@empresa.mx']);
        $this->service->delete($recipient);

        // La baja es lógica y no hay UNIQUE en la tabla justo para permitir
        // esto: la dirección vuelve como registro nuevo.
        $result = $this->service->createMany(['pagos@empresa.mx']);

        $this->assertCount(1, $result['created']);
        $this->assertSame([], $result['duplicates']);
    }

    public function test_el_endpoint_devuelve_el_desglose(): void
    {
        NotificationRecipient::create([
            'email' => 'pagos@empresa.mx',
            'isActive' => true,
            'createdAt' => Carbon::now(),
            'updatedAt' => Carbon::now(),
        ]);

        $result = $this->service->createMany([
            'pagos@empresa.mx',
            'nuevo@empresa.mx',
            'roto',
        ]);

        // Los tres grupos a la vez es el resultado NORMAL, no un caso raro:
        // por eso la respuesta los separa en vez de devolver «guardado».
        $this->assertCount(1, $result['created']);
        $this->assertCount(1, $result['duplicates']);
        $this->assertCount(1, $result['invalid']);
    }
}
