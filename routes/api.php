<?php

use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('health', fn () => response()->json(ApiResponse::success('OK', ['service' => config('app.name')])));

require __DIR__.'/api/auth.php';
require __DIR__.'/api/users.php';
require __DIR__.'/api/security.php';
require __DIR__.'/api/emailConfig.php';
require __DIR__.'/api/dashboard.php';
require __DIR__.'/api/exchangeRates.php';
require __DIR__.'/api/holidays.php';
require __DIR__.'/api/audit.php';
