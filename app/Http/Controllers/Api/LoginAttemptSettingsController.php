<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\UpdateLoginAttemptSettingsRequest;
use App\Http\Resources\LoginAttemptSettingResource;
use App\Models\LoginAttemptSetting;
use App\Services\LoginAttemptSettingsService;
use App\Support\ApiResponse;
use Illuminate\Support\Carbon;

/**
 * Configuración del Sistema → sesión y umbrales de autenticación.
 */
class LoginAttemptSettingsController extends Controller
{
    public function __construct(private readonly LoginAttemptSettingsService $service)
    {
    }

    /** GET /api/security/login-attempt-settings */
    public function show()
    {
        $settings = $this->service->getSettings();

        if ($settings->exists) {
            $settings->load('updatedBy');
        }

        return response()->json(ApiResponse::success(
            'Configuración de autenticación obtenida',
            LoginAttemptSettingResource::make($settings)
        ));
    }

    /** PUT /api/security/login-attempt-settings */
    public function update(UpdateLoginAttemptSettingsRequest $request)
    {
        $admin = $request->attributes->get('authUser');
        $data = $request->validated();
        $now = Carbon::now();

        $settings = LoginAttemptSetting::query()->orderBy('id')->first();

        if ($settings) {
            $settings->fill($data + ['updatedByUserId' => $admin?->id, 'updatedAt' => $now])->save();
        } else {
            $settings = LoginAttemptSetting::create(
                $data + ['updatedByUserId' => $admin?->id, 'createdAt' => $now, 'updatedAt' => $now]
            );
        }

        return response()->json(ApiResponse::success(
            'Configuración de autenticación actualizada',
            LoginAttemptSettingResource::make($settings->load('updatedBy'))
        ));
    }
}
