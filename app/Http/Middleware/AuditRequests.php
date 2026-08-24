<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditSanitizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Interceptor de solicitudes: deja una fila en `requestlogs` por cada petición
 * que modifica estado y enlaza con ella los cambios de registro que provocó.
 *
 * Corre en el grupo `api`, antes que `jwt`, así que el autor se lee de los
 * atributos de la petición ya resuelta.
 */
class AuditRequests
{
    public function __construct(
        private readonly AuditContext $context,
        private readonly AuditSanitizer $sanitizer,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldAudit($request)) {
            return $next($request);
        }

        $startedAt = microtime(true);
        $this->context->startCollecting((string) $request->ip());

        $response = $next($request);

        try {
            $this->context->identify(
                $request->attributes->get('authUser') instanceof User
                    ? $request->attributes->get('authUser')
                    : null,
                (string) $request->ip()
            );

            $log = $this->writeRequestLog($request, $response, $startedAt);
            $this->context->flush($log);
        } catch (Throwable $e) {
            // Nunca se altera la respuesta por un fallo de auditoría.
            Log::error('[Audit] no se pudo registrar la solicitud', [
                'method' => $request->method(),
                'path' => $request->path(),
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    private function writeRequestLog(Request $request, Response $response, float $startedAt): RequestLog
    {
        $actor = $this->context->actor();

        return RequestLog::create([
            'uuid' => (string) Str::uuid7(),
            'method' => $request->method(),
            'path' => Str::limit($request->path(), 250, ''),
            'routeName' => $request->route()?->getName(),
            'statusCode' => $response->getStatusCode(),
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
            'userId' => $actor['userId'],
            'actorName' => $actor['actorName'],
            'actorRole' => $actor['actorRole'],
            'ipAddress' => $actor['ipAddress'],
            'userAgent' => Str::limit((string) $request->userAgent(), 250, ''),
            'payload' => $this->sanitizer->payload($request->all()),
            'createdAt' => Carbon::now(),
        ]);
    }

    private function shouldAudit(Request $request): bool
    {
        if (!config('audit.enabled', true)) {
            return false;
        }

        $methods = array_map('strtoupper', (array) config('audit.methods', []));

        if (!in_array($request->method(), $methods, true)) {
            return false;
        }

        foreach ((array) config('audit.except', []) as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        return true;
    }
}
