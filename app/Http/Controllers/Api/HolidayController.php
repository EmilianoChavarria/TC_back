<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exchange\BulkHolidaysRequest;
use App\Http\Requests\Exchange\StoreHolidayRequest;
use App\Http\Requests\Exchange\UpdateHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use App\Services\Exchange\HolidayService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Días feriados. Determinan el día hábil siguiente en el cálculo del tipo de
 * cambio, por eso la consulta está abierta a cualquier usuario autenticado y
 * sólo la captura queda restringida.
 */
class HolidayController extends Controller
{
    public function __construct(private readonly HolidayService $holidays)
    {
    }

    /** GET /api/holidays?year=&includeDeleted= */
    public function index(Request $request)
    {
        $year = (int) ($request->query('year') ?: Carbon::today()->year);

        return response()->json(ApiResponse::success(
            "Días feriados de {$year}",
            HolidayResource::collection(
                $this->holidays->listByYear($year, $request->boolean('includeDeleted'))
            )
        ));
    }

    /**
     * GET /api/holidays/status
     *
     * Alimenta el aviso de la pantalla: años cubiertos, año pendiente y si el
     * recordatorio automático ya está activo.
     */
    public function status()
    {
        return response()->json(ApiResponse::success('Estado de captura', $this->holidays->status()));
    }

    /** POST /api/holidays */
    public function store(StoreHolidayRequest $request)
    {
        return response()->json(ApiResponse::success(
            'Día feriado registrado',
            HolidayResource::make($this->holidays->create($request->validated())),
            201
        ), 201);
    }

    /**
     * POST /api/holidays/bulk
     *
     * Captura del calendario completo de un año en una sola operación.
     * Los elementos con `uuid` se actualizan; el resto se dan de alta.
     */
    public function bulkStore(BulkHolidaysRequest $request)
    {
        $saved = $this->holidays->bulkSave($request->holidays());
        $year = (int) Carbon::parse($saved->first()->holidayDate)->year;

        return response()->json(ApiResponse::success(
            'Días feriados guardados',
            [
                'saved' => HolidayResource::collection($saved),
                'holidays' => HolidayResource::collection($this->holidays->listByYear($year)),
            ],
            201
        ), 201);
    }

    /** PUT /api/holidays/{uuid} */
    public function update(UpdateHolidayRequest $request, string $uuid)
    {
        $holiday = $this->find($uuid);

        if (!$holiday) {
            return response()->json(ApiResponse::error('Día feriado no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success(
            'Día feriado actualizado',
            HolidayResource::make($this->holidays->update($holiday, $request->validated()))
        ));
    }

    /** DELETE /api/holidays/{uuid} — baja lógica */
    public function destroy(string $uuid)
    {
        $holiday = $this->find($uuid);

        if (!$holiday) {
            return response()->json(ApiResponse::error('Día feriado no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success(
            'Día feriado eliminado',
            HolidayResource::make($this->holidays->delete($holiday))
        ));
    }

    /** POST /api/holidays/{uuid}/restore */
    public function restore(string $uuid)
    {
        $holiday = $this->find($uuid);

        if (!$holiday) {
            return response()->json(ApiResponse::error('Día feriado no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success(
            'Día feriado restaurado',
            HolidayResource::make($this->holidays->restore($holiday))
        ));
    }

    private function find(string $uuid): ?Holiday
    {
        return Holiday::query()->whereUuid($uuid)->first();
    }
}
