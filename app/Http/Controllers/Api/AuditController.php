<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\RequestLogResource;
use App\Models\AuditLog;
use App\Models\RequestLog;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Consulta del historial. Sólo SUPERADMIN y ADMIN.
 */
class AuditController extends Controller
{
    /**
     * GET /api/audit/logs
     *
     * Línea de tiempo de cambios. Filtros: table, recordUuid, event, actorUuid,
     * from, to. Es lo que alimenta el «Historial de la pantalla».
     */
    public function logs(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'table' => ['nullable', 'string', 'max:64'],
            'recordUuid' => ['nullable', 'uuid'],
            'event' => ['nullable', 'string', 'max:60'],
            'actorUuid' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Filtros inválidos', $validator->errors(), 422), 422);
        }

        $logs = AuditLog::query()
            ->with('requestLog')
            ->when($request->query('table'), fn ($q, $table) => $q->where('auditableTable', $table))
            ->when($request->query('recordUuid'), fn ($q, $uuid) => $q->where('auditableUuid', $uuid))
            ->when($request->query('event'), fn ($q, $event) => $q->where('event', $event))
            ->when($request->query('actorUuid'), fn ($q, $uuid) => $q->whereHas('user', fn ($u) => $u->where('uuid', $uuid)))
            ->when($request->query('from'), fn ($q, $from) => $q->where('createdAt', '>=', Carbon::parse($from)))
            ->when($request->query('to'), fn ($q, $to) => $q->where('createdAt', '<=', Carbon::parse($to)))
            ->orderByDesc('createdAt')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        $logs->setCollection(AuditLogResource::collection($logs->getCollection())->collection);

        return response()->json(ApiResponse::success('Historial obtenido', $logs));
    }

    /**
     * GET /api/audit/records/{table}/{uuid}
     *
     * Historial de un registro concreto, en orden cronológico inverso.
     */
    public function record(Request $request, string $table, string $uuid)
    {
        $logs = AuditLog::query()
            ->with('requestLog')
            ->forRecord($table, $uuid)
            ->orderByDesc('createdAt')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        $logs->setCollection(AuditLogResource::collection($logs->getCollection())->collection);

        return response()->json(ApiResponse::success('Historial del registro obtenido', $logs));
    }

    /**
     * GET /api/audit/requests
     *
     * Bitácora de solicitudes interceptadas, con los cambios que produjo cada una.
     */
    public function requests(Request $request)
    {
        $logs = RequestLog::query()
            ->with('auditLogs')
            ->when($request->query('method'), fn ($q, $method) => $q->where('method', strtoupper($method)))
            ->when($request->query('path'), fn ($q, $path) => $q->where('path', 'like', '%'.$path.'%'))
            ->when($request->query('actorUuid'), fn ($q, $uuid) => $q->whereHas('user', fn ($u) => $u->where('uuid', $uuid)))
            ->when($request->query('statusCode'), fn ($q, $status) => $q->where('statusCode', (int) $status))
            ->orderByDesc('createdAt')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        $logs->setCollection(RequestLogResource::collection($logs->getCollection())->collection);

        return response()->json(ApiResponse::success('Solicitudes obtenidas', $logs));
    }

    private function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->query('perPage', 20)));
    }
}
