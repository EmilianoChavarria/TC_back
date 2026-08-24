<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a los roles indicados. Debe correr después de "jwt".
 *
 *   Route::middleware(['jwt', 'role:SUPERADMIN,ADMIN'])
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->attributes->get('authUser');

        if (!$user instanceof User) {
            return response()->json(ApiResponse::error('Sesión no válida', null, 401), 401);
        }

        $allowed = array_map(static fn (string $role) => mb_strtoupper(trim($role)), $roles);

        if (!in_array($user->roleName(), $allowed, true)) {
            return response()->json(ApiResponse::error('No autorizado para esta operación', null, 403), 403);
        }

        return $next($request);
    }
}
