<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Exchange\PublicExchangeRateService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Consulta pública del tipo de cambio: sin sesión, sin cookie y sin nada que
 * revele el portal que hay detrás.
 *
 * Un solo endpoint de lectura. La forma de la respuesta la decide
 * PublicExchangeRateService, que es donde vive la regla de qué se publica.
 */
class PublicExchangeRateController extends Controller
{
    public function __construct(private readonly PublicExchangeRateService $rates)
    {
    }

    /** GET /api/public/exchange-rate */
    public function show(Request $request)
    {
        $days = (int) $request->query('days', PublicExchangeRateService::DEFAULT_DAYS);

        // El mensaje es deliberadamente neutro: ni nombres de módulos ni jerga
        // del sistema. Es una consulta pública, no una respuesta del portal.
        return response()->json(ApiResponse::success(
            'Tipo de cambio',
            $this->rates->snapshot($days)
        ));
    }
}
