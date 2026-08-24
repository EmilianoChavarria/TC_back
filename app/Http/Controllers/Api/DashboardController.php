<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Exchange\ExchangeDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Tablero de tipo de cambio. Abierto a cualquier usuario autenticado: es la
 * pantalla de entrada del sistema.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly ExchangeDashboardService $dashboard)
    {
    }

    /**
     * GET /api/dashboard/exchange-rate
     *
     * Todo lo que pinta la pantalla en una sola llamada: valor vigente de hoy
     * con su variación, día hábil siguiente, registros manuales del periodo,
     * estado del proceso automático y la serie de fluctuación.
     */
    public function exchangeRate(Request $request)
    {
        return response()->json(ApiResponse::success(
            'Tablero de tipo de cambio',
            $this->dashboard->summary($this->days($request))
        ));
    }

    /**
     * GET /api/dashboard/exchange-rate/fluctuation
     *
     * Sólo la serie, para recargar la gráfica con otra ventana de días sin
     * volver a pedir las tarjetas.
     */
    public function fluctuation(Request $request)
    {
        return response()->json(ApiResponse::success(
            'Fluctuación del tipo de cambio',
            $this->dashboard->fluctuation($this->days($request))
        ));
    }

    private function days(Request $request): int
    {
        return min(365, max(1, (int) $request->query('days', 30)));
    }
}
