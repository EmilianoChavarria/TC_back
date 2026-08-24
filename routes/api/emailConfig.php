<?php

use App\Http\Controllers\Api\EmailConfigController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

$admins = 'role:'.Role::SUPERADMIN.','.Role::ADMIN;

Route::middleware(['jwt', $admins])->group(function () {
    Route::get('email-config', [EmailConfigController::class, 'show']);
    Route::put('email-config', [EmailConfigController::class, 'update']);
    Route::post('email-config/test', [EmailConfigController::class, 'sendTest']);
});
