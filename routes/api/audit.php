<?php

use App\Http\Controllers\Api\AuditController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

$admins = 'role:'.Role::SUPERADMIN.','.Role::ADMIN;

Route::middleware(['jwt', $admins])->group(function () {
    Route::get('audit/logs', [AuditController::class, 'logs']);
    Route::get('audit/requests', [AuditController::class, 'requests']);
    Route::get('audit/records/{table}/{uuid}', [AuditController::class, 'record'])
        ->where('table', '[a-z_]+')
        ->whereUuid('uuid');
});
