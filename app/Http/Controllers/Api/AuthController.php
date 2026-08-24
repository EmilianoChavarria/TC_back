<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuthAttemptService;
use App\Services\JwtService;
use App\Services\LoginAttemptSettingsService;
use App\Services\PasswordValidationService;
use App\Support\ApiResponse;
use App\Support\AuthCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginUserAction $loginUserAction,
        private readonly RegisterUserAction $registerUserAction,
        private readonly LoginAttemptSettingsService $settingsService,
        private readonly AuthAttemptService $attempts,
        private readonly PasswordValidationService $passwords,
        private readonly JwtService $jwt,
    ) {
    }

    /** POST /api/auth/login */
    public function login(LoginRequest $request)
    {
        $result = $this->loginUserAction->execute($request->validated(), (string) $request->ip());

        if (!$result['ok']) {
            return response()->json(
                ApiResponse::error($result['message'], null, $result['status']),
                $result['status']
            );
        }

        return response()
            ->json(ApiResponse::success('Login exitoso', [
                'user' => $result['user'],
                'sessionTimeoutMinutes' => $result['sessionTimeoutMinutes'],
            ]))
            ->withCookie(AuthCookie::make($result['token'], (int) $result['sessionTimeoutMinutes']));
    }

    /** POST /api/auth/register — sólo SUPERADMIN y ADMIN */
    public function register(RegisterRequest $request)
    {
        try {
            $actor = $request->attributes->get('authUser');
            $result = $this->registerUserAction->execute($request->validated(), $actor?->roleName());
        } catch (ValidationException $e) {
            return response()->json(ApiResponse::error('Datos inválidos', $e->errors(), 422), 422);
        }

        return response()->json(ApiResponse::success('Usuario registrado', $result, 201), 201);
    }

    /** POST /api/auth/logout */
    public function logout(Request $request)
    {
        $user = $request->attributes->get('authUser');

        if ($user instanceof User) {
            $this->attempts->userSecurity((int) $user->id)
                ->fill(['sessionToken' => null, 'lastActivityAt' => Carbon::now()])
                ->save();
        }

        return response()
            ->json(ApiResponse::success('Sesión cerrada'))
            ->withCookie(AuthCookie::forget());
    }

    /**
     * GET /api/auth/verify
     *
     * Comprueba la sesión y renueva el token (sliding session). Responde 401
     * y limpia la cookie cuando el token ya no sirve.
     */
    public function verify(Request $request)
    {
        $user = $request->attributes->get('authUser');
        $tokenValid = (bool) $request->attributes->get('authTokenValid', false);

        if (!$user instanceof User || !$tokenValid) {
            $this->clearSessionByToken($request->attributes->get('authToken'));

            return response()
                ->json(ApiResponse::error('Sesión no válida', null, 401), 401)
                ->withCookie(AuthCookie::forget());
        }

        $timeoutMinutes = $this->settingsService->sessionTimeoutMinutes();
        $token = $this->jwt->issueToken((string) $user->uuid, $user->roleName(), $timeoutMinutes);

        $this->attempts->userSecurity((int) $user->id)->fill([
            'sessionToken' => $token,
            'lastActivityAt' => Carbon::now(),
            'lastKnownIp' => (string) $request->ip(),
        ])->save();

        return response()
            ->json(ApiResponse::success('Token renovado', [
                'isAuthenticated' => true,
                'sessionTimeoutMinutes' => $timeoutMinutes,
                'user' => [
                    'uuid' => (string) $user->uuid,
                    'fullName' => (string) $user->fullName,
                    'email' => (string) $user->email,
                    'roleName' => $user->roleName(),
                    'preferredLanguage' => (string) $user->preferredLanguage,
                    'mustChangePassword' => (bool) $user->mustChangePassword,
                    'passwordExpiresAt' => $this->passwords->expiresAt($user->passwordChangedAt)?->toIso8601String(),
                ],
            ]))
            ->withCookie(AuthCookie::make($token, $timeoutMinutes));
    }

    /** GET /api/auth/me */
    public function me(Request $request)
    {
        $user = $request->attributes->get('authUser');

        if (!$user instanceof User) {
            return response()->json(ApiResponse::error('Sesión no válida', null, 401), 401);
        }

        return response()->json(ApiResponse::success('Usuario autenticado', [
            'uuid' => (string) $user->uuid,
            'fullName' => (string) $user->fullName,
            'email' => (string) $user->email,
            'roleName' => $user->roleName(),
            'preferredLanguage' => (string) $user->preferredLanguage,
            'mustChangePassword' => (bool) $user->mustChangePassword,
        ]));
    }

    private function clearSessionByToken(mixed $token): void
    {
        if (!is_string($token) || $token === '') {
            return;
        }

        \App\Models\UserSecurity::query()
            ->where('sessionToken', $token)
            ->update(['sessionToken' => null, 'lastActivityAt' => Carbon::now()]);
    }
}
