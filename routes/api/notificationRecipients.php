<?php

use App\Http\Controllers\Api\NotificationRecipientController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

$admins = 'role:'.Role::SUPERADMIN.','.Role::ADMIN;

/*
 * Toda la sección es de administración: son direcciones de terceros que
 * reciben un dato financiero, y quién está en esa lista no es información que
 * deba ver cualquier usuario del portal.
 */
Route::middleware(['jwt', $admins])->group(function () {
    Route::get('notification-recipients', [NotificationRecipientController::class, 'index']);
    Route::post('notification-recipients', [NotificationRecipientController::class, 'store']);
    Route::post('notification-recipients/bulk', [NotificationRecipientController::class, 'bulkStore']);
    Route::put('notification-recipients/{uuid}', [NotificationRecipientController::class, 'update'])->whereUuid('uuid');
    Route::delete('notification-recipients/{uuid}', [NotificationRecipientController::class, 'destroy'])->whereUuid('uuid');
    Route::post('notification-recipients/{uuid}/restore', [NotificationRecipientController::class, 'restore'])->whereUuid('uuid');
});
