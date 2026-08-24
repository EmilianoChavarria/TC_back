<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlockedIpResource;
use App\Http\Resources\BlockedUserResource;
use App\Models\BlockedIp;
use App\Models\IpBlockedHistory;
use App\Models\User;
use App\Models\UserBlockedHistory;
use App\Models\UserSecurity;
use App\Services\AuthAttemptService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Panel de seguridad: bloqueos de usuarios e IPs.
 * Todas las rutas requieren rol SUPERADMIN o ADMIN.
 */
class AdminSecurityController extends Controller
{
    public function __construct(private readonly AuthAttemptService $attempts)
    {
    }

    /** GET /api/security/summary */
    public function summary()
    {
        return response()->json(ApiResponse::success('Resumen de seguridad', [
            'blockedUsers' => UserSecurity::query()->where('isBlocked', true)->count(),
            'blockedIps' => BlockedIp::query()->where('isBlockedPermanently', true)->count(),
            'attemptWindowHours' => (int) config('security.attempt_window_hours'),
        ]));
    }

    /**
     * GET /api/security/users/blocked
     *
     * Historial completo de bloqueos de usuario, incluidos los ya liberados.
     * ?onlyActive=1 deja solamente los que siguen bloqueados.
     */
    public function blockedUsers(Request $request)
    {
        $perPage = $this->perPage($request);
        $onlyActive = $request->boolean('onlyActive');

        $history = UserBlockedHistory::query()
            ->with(['user.role', 'user.security'])
            ->where('action', 'blocked')
            ->when($onlyActive, fn ($query) => $query->whereHas(
                'user.security',
                fn ($q) => $q->where('isBlocked', true)
            ))
            ->orderByDesc('createdAt')
            ->paginate($perPage);

        $history->setCollection(
            BlockedUserResource::collection($history->getCollection())->collection
        );

        return response()->json(ApiResponse::success('Usuarios bloqueados obtenidos', $history));
    }

    /**
     * GET /api/security/ips/blocked
     *
     * Historial de bloqueos de IP, incluidas las ya liberadas.
     * ?onlyActive=1 deja solamente las que siguen bloqueadas.
     */
    public function blockedIps(Request $request)
    {
        $perPage = $this->perPage($request);
        $onlyActive = $request->boolean('onlyActive');

        $query = IpBlockedHistory::query()->where('action', 'blocked');

        if ($onlyActive) {
            $activeIps = BlockedIp::query()
                ->where('isBlockedPermanently', true)
                ->pluck('ipAddress');

            $query->whereIn('ipAddress', $activeIps);
        }

        $history = $query->orderByDesc('createdAt')->paginate($perPage);

        $current = BlockedIp::query()
            ->whereIn('ipAddress', $history->getCollection()->pluck('ipAddress')->unique())
            ->get()
            ->keyBy('ipAddress');

        $history->setCollection(
            $history->getCollection()->map(
                fn (IpBlockedHistory $row) => BlockedIpResource::make($row, $current->get($row->ipAddress))
            )
        );

        return response()->json(ApiResponse::success('IPs bloqueadas obtenidas', $history));
    }

    /** POST /api/security/users/{uuid}/unlock */
    public function unlockUser(Request $request, string $uuid)
    {
        $admin = $request->attributes->get('authUser');
        $user = User::query()->whereUuid($uuid)->first();

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        if (!$this->attempts->unblockUser((int) $user->id, $admin?->id)) {
            return response()->json(ApiResponse::error('El usuario no tiene registro de seguridad', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Usuario desbloqueado'));
    }

    /** POST /api/security/ips/unlock */
    public function unlockIp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ipAddress' => ['required', 'string', 'max:45'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $admin = $request->attributes->get('authUser');
        $ip = (string) $request->input('ipAddress');

        if (!$this->attempts->unblockIp($ip, $admin?->id)) {
            return response()->json(ApiResponse::error('Dirección IP no encontrada', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Dirección IP desbloqueada'));
    }

    private function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->query('perPage', 15)));
    }
}
