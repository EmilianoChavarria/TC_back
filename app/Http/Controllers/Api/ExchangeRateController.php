<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exchange\ManualExchangeRateRequest;
use App\Http\Resources\ExchangeRateResource;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Exchange\BusinessDayService;
use App\Services\Exchange\ExchangeRateService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Gestión de Tipo de Cambio: consulta del histórico y captura manual del día en
 * curso o del día hábil siguiente.
 */
class ExchangeRateController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $rates,
        private readonly BusinessDayService $businessDays,
    ) {
    }

    /** GET /api/exchange-rates */
    public function index(Request $request)
    {
        $rates = ExchangeRate::query()
            ->with('manualSetBy')
            ->when(!$request->boolean('includeDeleted'), fn ($query) => $query->active())
            ->when($request->query('source'), fn ($query, $source) => $query->where('source', $source))
            ->when($request->query('from'), fn ($query, $from) => $query->where('applicableDate', '>=', Carbon::parse($from)->toDateString()))
            ->when($request->query('to'), fn ($query, $to) => $query->where('applicableDate', '<=', Carbon::parse($to)->toDateString()))
            ->when($request->query('month'), function ($query, $month) {
                $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

                return $query->whereBetween('applicableDate', [
                    $start->toDateString(),
                    $start->copy()->endOfMonth()->toDateString(),
                ]);
            })
            ->orderByDesc('applicableDate')
            ->paginate(min(100, max(1, (int) $request->query('perPage', 20))));

        $rates->setCollection(ExchangeRateResource::collection($rates->getCollection())->collection);

        return response()->json(ApiResponse::success('Tipos de cambio obtenidos', $rates));
    }

    /** GET /api/exchange-rates/current — tipo de cambio vigente de hoy */
    public function current()
    {
        $rate = $this->rates->current();

        if (!$rate) {
            return response()->json(ApiResponse::error('Aún no hay tipo de cambio para el día de hoy', null, 404), 404);
        }

        return response()->json(ApiResponse::success(
            'Tipo de cambio vigente',
            ExchangeRateResource::make($rate->load('manualSetBy'))
        ));
    }

    /** POST /api/exchange-rates — alta manual de una fecha sin cálculo previo */
    public function store(ManualExchangeRateRequest $request)
    {
        $data = $request->validated();
        $actor = $request->attributes->get('authUser');

        $rate = $this->rates->createManual(
            Carbon::createFromFormat('Y-m-d', $data['applicableDate']),
            (string) $data['manualRate'],
            (string) $data['reason'],
            $actor instanceof User ? $actor : null,
        );

        return response()->json(ApiResponse::success(
            'Tipo de cambio registrado',
            ExchangeRateResource::make($rate->load('manualSetBy')),
            201
        ), 201);
    }

    /** PUT /api/exchange-rates/{uuid} — corrección manual */
    public function update(ManualExchangeRateRequest $request, string $uuid)
    {
        $rate = $this->find($uuid);

        if (!$rate) {
            return response()->json(ApiResponse::error('Tipo de cambio no encontrado', null, 404), 404);
        }

        $data = $request->validated();
        $actor = $request->attributes->get('authUser');

        $rate = $this->rates->setManualRate(
            $rate,
            (string) $data['manualRate'],
            (string) $data['reason'],
            $actor instanceof User ? $actor : null,
        );

        return response()->json(ApiResponse::success(
            'Tipo de cambio actualizado',
            ExchangeRateResource::make($rate->load('manualSetBy'))
        ));
    }

    /** DELETE /api/exchange-rates/{uuid} — baja lógica */
    public function destroy(string $uuid)
    {
        $rate = $this->find($uuid);

        if (!$rate) {
            return response()->json(ApiResponse::error('Tipo de cambio no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success(
            'Tipo de cambio eliminado',
            ExchangeRateResource::make($this->rates->delete($rate))
        ));
    }

    /**
     * POST /api/exchange-rates/sync
     *
     * Ejecuta la sincronización con Banxico bajo demanda. El proceso diario hace
     * exactamente lo mismo de forma programada.
     */
    public function sync(Request $request)
    {
        $date = $request->input('date');

        try {
            $result = $this->rates->sync(
                $date ? Carbon::createFromFormat('Y-m-d', $date) : null,
                $request->integer('days') ?: null,
            );
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 502), 502);
        } catch (ValidationException $e) {
            return response()->json(ApiResponse::error('Datos inválidos', $e->errors(), 422), 422);
        }

        return response()->json(ApiResponse::success('Sincronización completada', $result));
    }

    /** GET /api/exchange-rates/editable-dates — fechas que admiten captura manual */
    public function editableDates()
    {
        $today = Carbon::today();

        return response()->json(ApiResponse::success('Fechas editables', [
            'today' => $today->toDateString(),
            'nextBusinessDay' => $this->businessDays->nextBusinessDay($today)->toDateString(),
        ]));
    }

    private function find(string $uuid): ?ExchangeRate
    {
        return ExchangeRate::query()->whereUuid($uuid)->first();
    }
}
