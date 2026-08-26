<?php

use App\Http\Controllers\Api\UserController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

$admins = 'role:'.Role::SUPERADMIN.','.Role::ADMIN;

Route::middleware(['jwt', $admins])->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/roles', [UserController::class, 'roles']);
    Route::get('users/{uuid}', [UserController::class, 'show'])->whereUuid('uuid');
    Route::put('users/{uuid}', [UserController::class, 'update'])->whereUuid('uuid');
    Route::delete('users/{uuid}', [UserController::class, 'destroy'])->whereUuid('uuid');
    Route::post('users/{uuid}/restore', [UserController::class, 'restore'])->whereUuid('uuid');
    Route::post('users/{uuid}/reset-password', [UserController::class, 'resetPassword'])->whereUuid('uuid');
});
