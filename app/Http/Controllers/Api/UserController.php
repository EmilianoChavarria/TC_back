<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Gestión de Usuarios. Sólo SUPERADMIN y ADMIN.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $users)
    {
    }

    /** GET /api/users */
    public function index(Request $request)
    {
        $users = $this->users->paginate([
            'search' => $request->query('search'),
            'roleName' => $request->query('roleName'),
            'status' => $request->query('status', 'active'),
            'perPage' => $request->query('perPage', 15),
        ]);

        $users->setCollection(UserResource::collection($users->getCollection())->collection);

        return response()->json(ApiResponse::success('Usuarios obtenidos', $users));
    }

    /** GET /api/users/roles — catálogo fijo para los formularios */
    public function roles()
    {
        $roles = Role::query()
            ->where('isActive', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => [
                'roleName' => $role->roleName,
                'description' => $role->description,
                'singleAccount' => in_array($role->roleName, Role::SINGLE_ACCOUNT, true),
            ]);

        return response()->json(ApiResponse::success('Roles obtenidos', $roles));
    }

    /** GET /api/users/{uuid} */
    public function show(string $uuid)
    {
        $user = $this->find($uuid);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Usuario obtenido', UserResource::make($user)));
    }

    /** PUT /api/users/{uuid} */
    public function update(UpdateUserRequest $request, string $uuid)
    {
        $user = $this->find($uuid);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        $updated = $this->users->update($user, $request->validated(), $this->actor($request));

        return response()->json(ApiResponse::success('Usuario actualizado', UserResource::make($updated)));
    }

    /** DELETE /api/users/{uuid} — baja lógica */
    public function destroy(Request $request, string $uuid)
    {
        $user = $this->find($uuid);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        $updated = $this->users->deactivate($user, $this->actor($request));

        return response()->json(ApiResponse::success('Usuario dado de baja', UserResource::make($updated)));
    }

    /** POST /api/users/{uuid}/restore */
    public function restore(Request $request, string $uuid)
    {
        $user = $this->find($uuid);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        $updated = $this->users->restore($user, $this->actor($request));

        return response()->json(ApiResponse::success('Usuario reactivado', UserResource::make($updated)));
    }

    /**
     * POST /api/users/{uuid}/reset-password
     *
     * Genera una contraseña temporal nueva y la envía por correo. La contraseña
     * nunca se devuelve en la respuesta.
     */
    public function resetPassword(string $uuid)
    {
        $user = $this->find($uuid);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        $sent = $this->users->resetPassword($user);

        if (!$sent) {
            return response()->json(ApiResponse::error(
                'La contraseña se restableció, pero el correo no salió. Revise la configuración de correo antes de continuar.',
                null,
                502,
            ), 502);
        }

        return response()->json(ApiResponse::success(
            'Contraseña restablecida; se envió por correo',
            UserResource::make($user->fresh(['role', 'security'])),
        ));
    }

    private function find(string $uuid): ?User
    {
        return User::query()->with(['role', 'security'])->whereUuid($uuid)->first();
    }

    private function actor(Request $request): ?User
    {
        $actor = $request->attributes->get('authUser');

        return $actor instanceof User ? $actor : null;
    }
}
