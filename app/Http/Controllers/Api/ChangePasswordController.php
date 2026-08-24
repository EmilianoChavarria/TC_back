<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\PasswordRequirementResource;
use App\Models\User;
use App\Services\JwtService;
use App\Services\LoginAttemptSettingsService;
use App\Services\PasswordValidationService;
use App\Support\ApiResponse;
use App\Support\AuthCookie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    public function __construct(
        private readonly PasswordValidationService $passwords,
        private readonly LoginAttemptSettingsService $settingsService,
        private readonly JwtService $jwt,
    ) {
    }

    /** POST /api/auth/change-password */
    public function __invoke(ChangePasswordRequest $request)
    {
        $user = $request->attributes->get('authUser');

        if (!$user instanceof User) {
            return response()->json(ApiResponse::error('Sesión no válida', null, 401), 401);
        }

        $data = $request->validated();

        if (!Hash::check((string) $data['currentPassword'], (string) $user->passwordHash)) {
            return response()->json(ApiResponse::error('La contraseña actual es incorrecta', null, 422), 422);
        }

        $errors = $this->passwords->validatePassword((string) $data['newPassword']);

        if ($errors !== []) {
            return response()->json(ApiResponse::error('La contraseña no cumple con los requisitos', [
                'newPassword' => $errors,
                'requirements' => PasswordRequirementResource::make($this->passwords->getRequirements()),
            ], 422), 422);
        }

        $now = Carbon::now();

        $user->fill([
            'passwordHash' => Hash::make((string) $data['newPassword']),
            'mustChangePassword' => false,
            'passwordChangedAt' => $now,
        ])->save();

        // Se emite un token nuevo: la sesión anterior deja de ser válida.
        $timeoutMinutes = $this->settingsService->sessionTimeoutMinutes();
        $token = $this->jwt->issueToken((string) $user->uuid, $user->roleName(), $timeoutMinutes);

        $user->security()->updateOrCreate(
            ['userId' => $user->id],
            ['sessionToken' => $token, 'lastActivityAt' => $now, 'lastKnownIp' => (string) $request->ip()]
        );

        return response()
            ->json(ApiResponse::success('Contraseña actualizada', [
                'passwordExpiresAt' => $this->passwords->expiresAt($now)?->toIso8601String(),
                'sessionTimeoutMinutes' => $timeoutMinutes,
            ]))
            ->withCookie(AuthCookie::make($token, $timeoutMinutes));
    }
}
