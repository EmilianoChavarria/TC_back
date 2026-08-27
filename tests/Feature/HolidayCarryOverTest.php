<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\Holiday;
use App\Services\Exchange\ExchangeRateService;
use App\Services\Exchange\HolidayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Arrastre del tipo de cambio en los días feriados del módulo.
 *
 * Lo que se prueba no es sólo que el feriado quede con el valor del día hábil
 * anterior, sino que el proceso NO pise lo que no le toca: una captura manual
 * hecha a conciencia, ni el histórico de días ya operados.
 */
class HolidayCarryOverTest extends TestCase
{
    use RefreshDatabase;

    private ExchangeRateService $rates;

    protected function setUp(): void
    {
        parent::setUp();

        // El servicio memoriza las fechas por instancia; se limpia entre casos.
        app(HolidayService::class)->forget();

        $this->rates = app(ExchangeRateService::class);
    }

    public function test_el_feriado_conserva_el_tipo_de_cambio_del_dia_habil_anterior(): void
    {
        // Miércoles 16/09/2026 feriado; el martes 15 tiene su publicación.
        $this->holiday('2026-09-16');
        $this->rate('2026-09-15', '18.5000');

        $carried = $this->rates->carryOverHolidays(
            Carbon::parse('2026-09-14'),
            Carbon::parse('2026-09-17')
        );

        $this->assertSame([['date' => '2026-09-16', 'from' => '2026-09-15']], $carried);

        $holidayRate = ExchangeRate::query()->whereDate('applicableDate', '2026-09-16')->first();

        $this->assertNotNull($holidayRate);
        $this->assertSame(ExchangeRate::SOURCE_CARRIED, $holidayRate->source);
        $this->assertSame('18.500000', (string) $holidayRate->effectiveRate);
        $this->assertSame('2026-09-15', $holidayRate->carriedFromDate->toDateString());
    }

    public function test_dos_feriados_seguidos_toman_el_mismo_dia_habil(): void
    {
        $this->holiday('2026-09-16');
        $this->holiday('2026-09-17');
        $this->rate('2026-09-15', '18.5000');

        $this->rates->carryOverHolidays(Carbon::parse('2026-09-14'), Carbon::parse('2026-09-18'));

        // El segundo feriado se apoya en el primero, que ya arrastraba el 15:
        // el valor tiene que ser el mismo en los tres días.
        $second = ExchangeRate::query()->whereDate('applicableDate', '2026-09-17')->first();

        $this->assertSame('18.500000', (string) $second->effectiveRate);
        $this->assertSame('2026-09-16', $second->carriedFromDate->toDateString());
    }

    public function test_no_pisa_la_captura_manual_del_feriado(): void
    {
        $future = Carbon::today()->addDays(3)->toDateString();

        $this->holiday($future);
        $this->rate(Carbon::today()->toDateString(), '18.5000');

        ExchangeRate::create([
            'applicableDate' => $future,
            'manualRate' => '19.000000',
            'effectiveRate' => '19.000000',
            'source' => ExchangeRate::SOURCE_MANUAL,
            'manualReason' => 'Instrucción de tesorería',
        ]);

        $this->rates->carryOverHolidays(Carbon::today(), Carbon::parse($future));

        $rate = ExchangeRate::query()->whereDate('applicableDate', $future)->first();

        $this->assertSame(ExchangeRate::SOURCE_MANUAL, $rate->source);
        $this->assertSame('19.000000', (string) $rate->effectiveRate);
    }

    public function test_no_reescribe_el_historico_ya_operado(): void
    {
        // Fecha pasada que después se marcó como feriado: el valor con el que
        // se operó ese día es un hecho, no se corrige hacia atrás.
        $past = Carbon::today()->subDays(5)->toDateString();

        $this->holiday($past);
        $this->rate(Carbon::today()->subDays(6)->toDateString(), '18.0000');
        $this->rate($past, '18.9000');

        $this->rates->carryOverHolidays(Carbon::today()->subDays(10), Carbon::today());

        $rate = ExchangeRate::query()->whereDate('applicableDate', $past)->first();

        $this->assertSame(ExchangeRate::SOURCE_AUTOMATIC, $rate->source);
        $this->assertSame('18.900000', (string) $rate->effectiveRate);
    }

    public function test_el_feriado_futuro_ya_arrastrado_se_actualiza_con_la_publicacion_mas_reciente(): void
    {
        $today = Carbon::today();
        $holiday = $this->nextWeekdayFrom($today->copy()->addDay());

        $this->holiday($holiday->toDateString());
        $this->rate($today->copy()->subDays(1)->toDateString(), '18.0000');

        $this->rates->carryOverHolidays($today->copy()->subDays(2), $holiday);

        // Por la tarde llega la publicación que aplica a hoy: el feriado tiene
        // que quedarse con ésa, no con la de la mañana.
        $this->rate($today->toDateString(), '18.7000');

        $this->rates->carryOverHolidays($today->copy()->subDays(2), $holiday);

        $rate = ExchangeRate::query()->whereDate('applicableDate', $holiday->toDateString())->first();

        $this->assertSame('18.700000', (string) $rate->effectiveRate);
        $this->assertSame($today->toDateString(), $rate->carriedFromDate->toDateString());
    }

    public function test_sin_tipo_de_cambio_previo_no_inventa_registro(): void
    {
        $this->holiday('2026-09-16');

        $carried = $this->rates->carryOverHolidays(
            Carbon::parse('2026-09-14'),
            Carbon::parse('2026-09-17')
        );

        $this->assertSame([], $carried);
        $this->assertDatabaseCount('exchangerates', 0);
    }

    // ---------------------------------------------------------------- apoyos

    private function holiday(string $date): Holiday
    {
        $holiday = Holiday::create([
            'holidayDate' => $date,
            'description' => 'Prueba',
        ]);

        app(HolidayService::class)->forget();

        return $holiday;
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

    /** Primer día entre semana desde la fecha dada, para no caer en sábado. */
    private function nextWeekdayFrom(Carbon $date): Carbon
    {
        $cursor = $date->copy()->startOfDay();

        while ($cursor->isWeekend()) {
            $cursor->addDay();
        }

        return $cursor;
    }
}
