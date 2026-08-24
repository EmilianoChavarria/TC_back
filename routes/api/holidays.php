<?php

use App\Http\Controllers\Api\HolidayController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

$admins = 'role:'.Role::SUPERADMIN.','.Role::ADMIN;

Route::middleware('jwt')->group(function () use ($admins) {
    // Consulta abierta: el calendario afecta el cálculo del tipo de cambio que
    // todos consultan.
    Route::get('holidays', [HolidayController::class, 'index']);
    Route::get('holidays/status', [HolidayController::class, 'status']);

    Route::middleware($admins)->group(function () {
        Route::post('holidays', [HolidayController::class, 'store']);
        Route::post('holidays/bulk', [HolidayController::class, 'bulkStore']);
        Route::put('holidays/{uuid}', [HolidayController::class, 'update'])->whereUuid('uuid');
        Route::delete('holidays/{uuid}', [HolidayController::class, 'destroy'])->whereUuid('uuid');
        Route::post('holidays/{uuid}/restore', [HolidayController::class, 'restore'])->whereUuid('uuid');
    });
});
