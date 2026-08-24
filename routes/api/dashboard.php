<?php

use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt')->group(function () {
    Route::get('dashboard/exchange-rate', [DashboardController::class, 'exchangeRate']);
    Route::get('dashboard/exchange-rate/fluctuation', [DashboardController::class, 'fluctuation']);
});
