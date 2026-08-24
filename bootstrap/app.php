<?php

use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Interceptor de auditoría: envuelve todo el grupo api, así que ve la
        // respuesta final y el usuario ya resuelto por `jwt`.
        $middleware->api(append: [
            \App\Http\Middleware\AuditRequests::class,
        ]);

        $middleware->alias([
            'jwt' => \App\Http\Middleware\JwtAuth::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Respuesta uniforme para errores de validación.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(ApiResponse::error('Datos inválidos', $e->errors(), 422), 422);
            }

            return null;
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: match ($status) {
                401 => 'No autenticado',
                403 => 'No autorizado',
                404 => 'Recurso no encontrado',
                405 => 'Método no permitido',
                429 => 'Demasiadas solicitudes, intente más tarde',
                default => 'Error en la solicitud',
            };

            return response()->json(ApiResponse::error($message, null, $status), $status);
        });

        // Cualquier otro error: el detalle interno nunca se filtra en producción.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            $debug = (bool) config('app.debug');

            return response()->json(ApiResponse::error(
                'Error inesperado del servidor',
                $debug ? [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
                500
            ), 500);
        });
    })->create();
