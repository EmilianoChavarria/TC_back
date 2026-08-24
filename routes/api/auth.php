<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChangePasswordController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

$admins = 'role:'.Role::SUPERADMIN.','.Role::ADMIN;

// Público
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

// Requiere sesión
Route::middleware('jwt')->group(function () use ($admins) {
    Route::get('auth/verify', [AuthController::class, 'verify']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/change-password', ChangePasswordController::class);

    // Alta de usuarios: sólo SUPERADMIN y ADMIN
    Route::post('auth/register', [AuthController::class, 'register'])->middleware($admins);
});
