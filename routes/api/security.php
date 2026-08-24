<?php

use App\Http\Controllers\Api\AdminSecurityController;
use App\Http\Controllers\Api\LoginAttemptSettingsController;
use App\Http\Controllers\Api\PasswordRequirementsController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

$admins = 'role:'.Role::SUPERADMIN.','.Role::ADMIN;

// Pública: el formulario de login/registro valida la contraseña en vivo.
Route::post('password-requirements/validate', [PasswordRequirementsController::class, 'validatePassword'])
    ->middleware('throttle:60,1');

Route::middleware('jwt')->group(function () use ($admins) {
    // Cualquier usuario autenticado necesita conocer los requisitos vigentes
    // para el formulario de cambio de contraseña.
    Route::get('password-requirements', [PasswordRequirementsController::class, 'show']);

    Route::middleware($admins)->group(function () {
        Route::put('password-requirements', [PasswordRequirementsController::class, 'update']);

        Route::get('security/login-attempt-settings', [LoginAttemptSettingsController::class, 'show']);
        Route::put('security/login-attempt-settings', [LoginAttemptSettingsController::class, 'update']);

        Route::get('security/summary', [AdminSecurityController::class, 'summary']);
        Route::get('security/users/blocked', [AdminSecurityController::class, 'blockedUsers']);
        Route::get('security/ips/blocked', [AdminSecurityController::class, 'blockedIps']);
        Route::post('security/users/{uuid}/unlock', [AdminSecurityController::class, 'unlockUser'])->whereUuid('uuid');
        Route::post('security/ips/unlock', [AdminSecurityController::class, 'unlockIp']);
    });
});
