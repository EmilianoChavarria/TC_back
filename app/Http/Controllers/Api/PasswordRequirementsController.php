<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\UpdatePasswordRequirementsRequest;
use App\Http\Resources\PasswordRequirementResource;
use App\Models\PasswordRequirement;
use App\Services\PasswordValidationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Configuración del Sistema → requisitos de contraseña.
 */
class PasswordRequirementsController extends Controller
{
    public function __construct(private readonly PasswordValidationService $passwords)
    {
    }

    /** GET /api/password-requirements */
    public function show()
    {
        $requirements = $this->passwords->getRequirements();

        if ($requirements->exists) {
            $requirements->load('updatedBy');
        }

        return response()->json(ApiResponse::success(
            'Requisitos de contraseña obtenidos',
            PasswordRequirementResource::make($requirements)
        ));
    }

    /** PUT /api/password-requirements — sólo SUPERADMIN y ADMIN */
    public function update(UpdatePasswordRequirementsRequest $request)
    {
        $admin = $request->attributes->get('authUser');
        $data = $request->validated();
        $now = Carbon::now();

        if (($data['requireSpecialChars'] ?? false) && trim((string) ($data['allowedSpecialChars'] ?? '')) === '') {
            $data['allowedSpecialChars'] = (string) config('security.password_defaults.allowedSpecialChars');
        }

        $requirements = PasswordRequirement::query()->orderBy('id')->first();

        if ($requirements) {
            $requirements->fill($data + ['updatedByUserId' => $admin?->id, 'updatedAt' => $now])->save();
        } else {
            $requirements = PasswordRequirement::create(
                $data + ['updatedByUserId' => $admin?->id, 'createdAt' => $now, 'updatedAt' => $now]
            );
        }

        return response()->json(ApiResponse::success(
            'Requisitos de contraseña actualizados',
            PasswordRequirementResource::make($requirements->load('updatedBy'))
        ));
    }

    /** POST /api/password-requirements/validate — pública, para validar en vivo desde el formulario */
    public function validatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Contraseña requerida', $validator->errors(), 422), 422);
        }

        $requirements = $this->passwords->getRequirements();
        $errors = $this->passwords->validatePassword((string) $request->input('password'), $requirements);

        if ($errors === []) {
            return response()->json(ApiResponse::success('La contraseña cumple con los requisitos', [
                'isValid' => true,
                'requirements' => PasswordRequirementResource::make($requirements),
            ]));
        }

        return response()->json(ApiResponse::error('La contraseña no cumple con los requisitos', [
            'isValid' => false,
            'errors' => $errors,
            'requirements' => PasswordRequirementResource::make($requirements),
        ], 422), 422);
    }
}
