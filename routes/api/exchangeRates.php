<?php

use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\ExchangeRateFactorController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

$admins = 'role:'.Role::SUPERADMIN.','.Role::ADMIN;

Route::middleware('jwt')->group(function () use ($admins) {
    // Consulta: cualquier usuario autenticado necesita el tipo de cambio vigente.
    Route::get('exchange-rates/current', [ExchangeRateController::class, 'current']);
    Route::get('exchange-rates', [ExchangeRateController::class, 'index']);
    Route::get('exchange-rates/editable-dates', [ExchangeRateController::class, 'editableDates']);
    Route::get('exchange-rates/factors', [ExchangeRateFactorController::class, 'index']);

    // Administración
    Route::middleware($admins)->group(function () {
        Route::post('exchange-rates', [ExchangeRateController::class, 'store']);
        Route::put('exchange-rates/{uuid}', [ExchangeRateController::class, 'update'])->whereUuid('uuid');
        Route::delete('exchange-rates/{uuid}', [ExchangeRateController::class, 'destroy'])->whereUuid('uuid');
        Route::post('exchange-rates/sync', [ExchangeRateController::class, 'sync']);

        Route::post('exchange-rates/factors', [ExchangeRateFactorController::class, 'store']);
        Route::post('exchange-rates/factors/bulk', [ExchangeRateFactorController::class, 'bulkStore']);
        Route::put('exchange-rates/factors/{uuid}', [ExchangeRateFactorController::class, 'update'])->whereUuid('uuid');
        Route::delete('exchange-rates/factors/{uuid}', [ExchangeRateFactorController::class, 'destroy'])->whereUuid('uuid');
        Route::post('exchange-rates/factors/{uuid}/restore', [ExchangeRateFactorController::class, 'restore'])->whereUuid('uuid');
    });
});
