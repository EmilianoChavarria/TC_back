<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Consulta pública del tipo de cambio.
 *
 * La prueba que importa aquí no es que responda: es que **no filtre**. La
 * respuesta va a internet abierta, así que se verifica campo por campo que no
 * salga nada del portal —identificadores, origen del dato, capturas manuales,
 * motivos ni autores— por mucho que esas columnas existan en el registro.
 */
class PublicExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    /** Todo lo que jamás debe aparecer en la respuesta pública. */
    private const PROHIBIDO = [
        'uuid',
        'source',
        'sourceLabel',
        'manualRate',
        'manualReason',
        'manualSetAt',
        'manualSetBy',
        'lastModifiedBy',
        'publishedRate',
        'publishedDate',
        'calculatedRate',
        'effectiveRate',
        'factorCode',
        'carriedFromDate',
        'notifiedAt',
        'notifiedRate',
        'isEditable',
        'deletedAt',
    ];

    public function test_responde_sin_sesion(): void
    {
        $this->rate(Carbon::today()->toDateString(), '18.8242');

        $response = $this->getJson('/api/public/exchange-rate');

        $response->assertOk();
        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.rate', '18.8242');
        $response->assertJsonPath('data.currency', 'MXN/USD');
    }

    public function test_no_expone_nada_del_portal(): void
    {
        Role::create(['roleName' => Role::USER, 'description' => 'Usuario', 'isActive' => true]);

        $user = User::factory()->create();

        $this->rate(Carbon::today()->subDay()->toDateString(), '18.5000');

        // Registro con TODO lo que el portal guarda: si algo se filtrara, este
        // es el caso en el que aparecería.
        ExchangeRate::create([
            'applicableDate' => Carbon::today()->toDateString(),
            'publishedRate' => '18.7000',
            'publishedDate' => Carbon::today()->subDay()->toDateString(),
            'factorCode' => 5,
            'factorValue' => '1.004000',
            'calculatedRate' => '18.7000',
            'manualRate' => '18.8242',
            'effectiveRate' => '18.8242',
            'source' => ExchangeRate::SOURCE_MANUAL,
            'manualReason' => 'Instrucción de tesorería',
            'manualSetByUserId' => $user->id,
            'manualSetAt' => Carbon::now(),
        ]);

        $body = $this->getJson('/api/public/exchange-rate')->json('data');
        $flat = json_encode($body, JSON_UNESCAPED_UNICODE);

        foreach (self::PROHIBIDO as $campo) {
            $this->assertStringNotContainsString(
                '"'.$campo.'"',
                (string) $flat,
                "La respuesta pública expone «{$campo}»"
            );
        }

        // Tampoco el contenido: el motivo y el autor son del área privada.
        $this->assertStringNotContainsString('Instrucción de tesorería', (string) $flat);
        $this->assertStringNotContainsString((string) $user->fullName, (string) $flat);
        $this->assertStringNotContainsString((string) $user->email, (string) $flat);
        $this->assertStringNotContainsString((string) $user->uuid, (string) $flat);

        // El valor vigente sí sale, y es el que se usa para operar.
        $this->assertSame('18.8242', $body['rate']);
        $this->assertSame('1.004', $body['factor']);
    }

    public function test_incluye_maximo_minimo_y_variacion_del_periodo(): void
    {
        $this->rate(Carbon::today()->subDays(3)->toDateString(), '18.4000');
        $this->rate(Carbon::today()->subDays(2)->toDateString(), '18.9000');
        $this->rate(Carbon::today()->toDateString(), '18.6000');

        $data = $this->getJson('/api/public/exchange-rate')->json('data');

        $this->assertSame('18.4000', $data['window']['min']);
        $this->assertSame('18.9000', $data['window']['max']);
        $this->assertSame(30, $data['window']['days']);

        // La variación es contra el registro vigente anterior, no contra el
        // primero de la serie.
        $this->assertSame('-0.3000', $data['change']['amount']);
        $this->assertSame('down', $data['change']['direction']);

        // El historial se lee del más reciente al más antiguo.
        $this->assertSame(
            [Carbon::today()->toDateString(), Carbon::today()->subDays(2)->toDateString()],
            array_slice(array_column($data['history'], 'date'), 0, 2)
        );
    }

    public function test_en_dia_sin_registro_responde_el_ultimo_disponible(): void
    {
        // Fin de semana o feriado: la consulta pública no puede quedarse muda.
        $this->rate(Carbon::today()->subDays(4)->toDateString(), '18.1234');

        $data = $this->getJson('/api/public/exchange-rate')->json('data');

        $this->assertTrue($data['available']);
        $this->assertSame('18.1234', $data['rate']);
        $this->assertSame(Carbon::today()->subDays(4)->toDateString(), $data['date']);
    }

    public function test_sin_datos_no_falla(): void
    {
        $response = $this->getJson('/api/public/exchange-rate');

        $response->assertOk();
        $response->assertJsonPath('data.available', false);
    }

    private function rate(string $date, string $value): ExchangeRate
    {
        return ExchangeRate::create([
            'applicableDate' => $date,
            'publishedRate' => $value,
            'publishedDate' => Carbon::parse($date)->subDay()->toDateString(),
            'calculatedRate' => $value,
            'effectiveRate' => $value,
            'source' => ExchangeRate::SOURCE_AUTOMATIC,
        ]);
    }
}
