<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exchange\BulkFactorsRequest;
use App\Http\Requests\Exchange\StoreFactorRequest;
use App\Http\Requests\Exchange\UpdateFactorRequest;
use App\Http\Resources\ExchangeRateFactorResource;
use App\Models\ExchangeRateFactor;
use App\Services\Exchange\ExchangeRateFactorService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Administración de Factores: rangos de la publicación de Banxico y el factor
 * que se aplica en cada uno. Límite inferior inclusivo, superior exclusivo; los
 * rangos vigentes no pueden traslaparse.
 */
class ExchangeRateFactorController extends Controller
{
    public function __construct(private readonly ExchangeRateFactorService $factors)
    {
    }

    /** GET /api/exchange-rates/factors */
    public function index(Request $request)
    {
        $factors = ExchangeRateFactor::query()
            ->when(!$request->boolean('includeDeleted'), fn ($query) => $query->active())
            ->orderBy('rangeFrom')
            ->get();

        return response()->json(ApiResponse::success(
            'Factores obtenidos',
            ExchangeRateFactorResource::collection($factors)
        ));
    }

    /** POST /api/exchange-rates/factors */
    public function store(StoreFactorRequest $request)
    {
        $factor = $this->factors->create($request->validated());

        return response()->json(ApiResponse::success(
            'Factor registrado',
            ExchangeRateFactorResource::make($factor),
            201
        ), 201);
    }

    /**
     * POST /api/exchange-rates/factors/bulk
     *
     * Guarda varios factores de una sola vez. Los elementos con `uuid` se
     * actualizan y el resto se dan de alta. Es todo o nada: si algún rango es
     * inválido o el conjunto resultante se traslapa, no se guarda ninguno.
     */
    public function bulkStore(BulkFactorsRequest $request)
    {
        $saved = $this->factors->bulkSave($request->factors());

        return response()->json(ApiResponse::success(
            'Factores guardados',
            [
                'saved' => ExchangeRateFactorResource::collection($saved),
                'factors' => ExchangeRateFactorResource::collection(
                    ExchangeRateFactor::query()->active()->orderBy('rangeFrom')->get()
                ),
            ],
            201
        ), 201);
    }

    /** PUT /api/exchange-rates/factors/{uuid} */
    public function update(UpdateFactorRequest $request, string $uuid)
    {
        $factor = $this->find($uuid);

        if (!$factor) {
            return response()->json(ApiResponse::error('Factor no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success(
            'Factor actualizado',
            ExchangeRateFactorResource::make($this->factors->update($factor, $request->validated()))
        ));
    }

    /** DELETE /api/exchange-rates/factors/{uuid} — baja lógica */
    public function destroy(string $uuid)
    {
        $factor = $this->find($uuid);

        if (!$factor) {
            return response()->json(ApiResponse::error('Factor no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success(
            'Factor eliminado',
            ExchangeRateFactorResource::make($this->factors->delete($factor))
        ));
    }

    /** POST /api/exchange-rates/factors/{uuid}/restore */
    public function restore(string $uuid)
    {
        $factor = $this->find($uuid);

        if (!$factor) {
            return response()->json(ApiResponse::error('Factor no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success(
            'Factor restaurado',
            ExchangeRateFactorResource::make($this->factors->restore($factor))
        ));
    }

    private function find(string $uuid): ?ExchangeRateFactor
    {
        return ExchangeRateFactor::query()->whereUuid($uuid)->first();
    }
}
