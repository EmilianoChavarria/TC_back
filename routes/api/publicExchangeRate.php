<?php

use App\Http\Controllers\Api\PublicExchangeRateController;
use Illuminate\Support\Facades\Route;

/*
 | Consulta pública: sin `jwt` a propósito. Va con límite de peticiones porque
 | es la única ruta abierta a cualquiera en internet.
 */
Route::get('public/exchange-rate', [PublicExchangeRateController::class, 'show'])
    ->middleware('throttle:60,1');
