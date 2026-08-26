<?php

namespace App\Actions\Auth;

use App\Mail\UserRegisteredMail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSecurity;
use App\Services\EmailSenderService;
use App\Services\PasswordValidationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterUserAction
{
    public function __construct(
        private readonly PasswordValidationService $passwords,
        private readonly EmailSenderService $emailSender,
    ) {
    }

    /**
     * Alta de usuario. Si no se envía contraseña se genera una temporal que
     * cumple los requisitos vigentes y se envía por correo.
     *
     * @param string|null $actorRoleName rol de quien da el alta; sólo SUPERADMIN
     *                                   puede crear cuentas privilegiadas.
     */
    public function execute(array $data, ?string $actorRoleName = null): array
    {
        return DB::transaction(function () use ($data, $actorRoleName) {
            $roleName = mb_strtoupper(trim((string) $data['roleName']));
            $role = Role::query()->where('roleName', $roleName)->first();

            if (!$role || !$role->isActive) {
                throw ValidationException::withMessages([
                    'roleName' => ['El rol indicado no existe o está inactivo'],
                ]);
            }

            $privileged = in_array($roleName, Role::NEVER_BLOCKED, true);

            if ($privileged && $actorRoleName !== Role::SUPERADMIN) {
                throw ValidationException::withMessages([
                    'roleName' => ['Sólo un SUPERADMIN puede crear cuentas con rol '.$roleName],
                ]);
            }

            // Del superadministrador y del administrador sólo existe una cuenta
            // activa; el resto de la organización son usuarios.
            if (in_array($roleName, Role::SINGLE_ACCOUNT, true)) {
                $taken = User::query()
                    ->where('roleId', $role->id)
                    ->where('isActive', true)
                    ->whereNull('deletedAt')
                    ->where('email', '!=', $data['email'])
                    ->exists();

                if ($taken) {
                    throw ValidationException::withMessages([
                        'roleName' => ["Ya existe una cuenta activa con rol {$roleName}; désela de baja antes de crear otra."],
                    ]);
                }
            }

            $generated = empty($data['password']);
            $password = $generated
                ? $this->passwords->generateCompliantPassword()
                : (string) $data['password'];

            $passwordErrors = $this->passwords->validatePassword($password);

            if ($passwordErrors !== []) {
                throw ValidationException::withMessages([
                    'password' => $passwordErrors,
                ]);
            }

            $existing = User::query()->where('email', $data['email'])->first();

            if ($existing && $existing->isActive && !$existing->deletedAt) {
                throw ValidationException::withMessages([
                    'email' => ['El correo electrónico ya está registrado'],
                ]);
            }

            $now = Carbon::now();
            $attributes = [
                'fullName' => (string) $data['fullName'],
                'passwordHash' => Hash::make($password),
                'roleId' => (int) $role->id,
                'isActive' => true,
                'mustChangePassword' => true,
                'passwordChangedAt' => $now,
                'deletedAt' => null,
            ];

            if ($existing) {
                // Reactivación de una cuenta dada de baja.
                $existing->fill($attributes)->save();
                $user = $existing->refresh();
            } else {
                $user = User::create($attributes + ['email' => (string) $data['email']]);
            }

            UserSecurity::query()->updateOrCreate(
                ['userId' => $user->id],
                [
                    'sessionToken' => null,
                    'failedAttempts' => 0,
                    'lastFailedAt' => null,
                    'isBlocked' => false,
                    'blockedAt' => null,
                    'blockedReason' => null,
                ]
            );

            $this->emailSender->send(
                new UserRegisteredMail(
                    (string) $user->fullName,
                    (string) $user->email,
                    $password
                ),
                (string) $user->email
            );

            return [
                'uuid' => (string) $user->uuid,
                'fullName' => (string) $user->fullName,
                'email' => (string) $user->email,
                'roleName' => (string) $role->roleName,
                'passwordGenerated' => $generated,
            ];
        });
    }
}
