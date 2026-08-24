<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuthAttemptService;
use App\Services\LoginAttemptSettingsService;
use App\Support\ApiResponse;
use App\Support\AuthCookie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Valida el JWT (cabecera Authorization o cookie httpOnly), la sesión activa
 * en base de datos, el bloqueo de usuario/IP y la inactividad máxima.
 */
class JwtAuth
{
    public function __construct(
        private readonly LoginAttemptSettingsService $settingsService,
        private readonly AuthAttemptService $attempts,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);
        $timeoutMinutes = $this->settingsService->sessionTimeoutMinutes();

        $request->attributes->set('authToken', $token);
        $request->attributes->set('authTokenValid', false);
        $request->attributes->set('sessionTimeoutMinutes', $timeoutMinutes);

        // El bloqueo de IP aplica a todos en el login. Aquí se evalúa después de
        // identificar al usuario: una sesión activa de SUPERADMIN/ADMIN sigue
        // pasando para que puedan liberar la IP desde el panel de seguridad.
        $ipBlocked = $this->attempts->isIpBlocked((string) $request->ip());

        if (!$token) {
            return $this->reject($request, $next, 'Token no proporcionado', 401);
        }

        try {
            $claims = app(\App\Services\JwtService::class)->decodeToken($token);
        } catch (Throwable) {
            return $this->reject($request, $next, 'Token inválido o expirado', 401);
        }

        $user = User::query()->with('role')->whereUuid((string) ($claims->sub ?? ''))->first();

        if (!$user || !$user->isActive || $user->deletedAt) {
            return $this->reject($request, $next, 'Usuario no autorizado', 403);
        }

        if ($ipBlocked && !$user->isSecurityAdmin()) {
            return $this->reject($request, $next, 'Dirección IP bloqueada. Contacte al administrador.', 423);
        }

        $security = $this->attempts->userSecurity((int) $user->id);

        if ((bool) $security->isBlocked) {
            return $this->reject($request, $next, 'Usuario bloqueado. Contacte al administrador.', 423);
        }

        // Sesión única: el token debe ser el último emitido para el usuario.
        if ((string) $security->sessionToken !== $token) {
            return $this->reject($request, $next, 'Sesión no válida', 401);
        }

        // Cierre automático por inactividad.
        if ($security->lastActivityAt && $security->lastActivityAt->copy()->addMinutes($timeoutMinutes)->isPast()) {
            $security->fill(['sessionToken' => null, 'lastActivityAt' => Carbon::now()])->save();

            return $this->reject($request, $next, 'Sesión expirada por inactividad', 401);
        }

        $security->fill([
            'lastActivityAt' => Carbon::now(),
            'lastKnownIp' => (string) $request->ip(),
        ])->save();

        $request->attributes->set('authUser', $user);
        $request->attributes->set('authRole', $user->roleName());
        $request->attributes->set('authTokenValid', true);
        $request->setUserResolver(fn () => $user);

        if ($user->mustChangePassword && !$this->isPasswordChangeExempt($request)) {
            return response()->json(
                ApiResponse::error('Debe cambiar su contraseña antes de continuar', ['mustChangePassword' => true], 403),
                403
            );
        }

        return $next($request);
    }

    /**
     * /auth/verify necesita continuar aunque el token no sirva, para poder
     * responder 401 y limpiar la cookie del navegador.
     */
    private function reject(Request $request, Closure $next, string $message, int $status): Response
    {
        if ($this->isVerifyRoute($request)) {
            return $next($request);
        }

        $response = response()->json(ApiResponse::error($message, null, $status), $status);

        return $status === 401 ? $response->withCookie(AuthCookie::forget()) : $response;
    }

    private function extractToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));

            if ($token !== '') {
                return $token;
            }
        }

        $cookie = $request->cookie(AuthCookie::name());

        return is_string($cookie) && $cookie !== '' ? $cookie : null;
    }

    private function isVerifyRoute(Request $request): bool
    {
        return str_ends_with($request->path(), 'auth/verify');
    }

    private function isPasswordChangeExempt(Request $request): bool
    {
        $path = $request->path();

        return str_ends_with($path, 'auth/logout')
            || str_ends_with($path, 'auth/verify')
            || str_ends_with($path, 'auth/change-password')
            || str_ends_with($path, 'password-requirements');
    }
}
