<?php

namespace App\Services;

use App\Mail\UserRegisteredMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Administración de cuentas: consulta, edición, baja lógica y restablecimiento
 * de la contraseña temporal.
 *
 * Las reglas de rol viven aquí y en RegisterUserAction: sólo una cuenta activa
 * de SUPERADMIN y una de ADMIN, y sólo un SUPERADMIN toca cuentas privilegiadas.
 */
class UserService
{
    public function __construct(
        private readonly PasswordValidationService $passwords,
        private readonly EmailSenderService $emailSender,
        private readonly AuthAttemptService $attempts,
    ) {
    }

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(100, max(1, (int) ($filters['perPage'] ?? 15)));
        $status = (string) ($filters['status'] ?? 'active');

        return User::query()
            ->with(['role', 'security'])
            // El superadministrador no se lista: es la cuenta raíz del sistema y
            // no se administra desde esta pantalla.
            ->whereHas('role', fn ($inner) => $inner->where('roleName', '!=', Role::SUPERADMIN))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $term = '%'.$search.'%';

                $query->where(function ($inner) use ($term) {
                    $inner->where('fullName', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->when($filters['roleName'] ?? null, fn ($query, $role) => $query->whereHas(
                'role',
                fn ($inner) => $inner->where('roleName', mb_strtoupper((string) $role)),
            ))
            ->when($status === 'active', fn ($query) => $query->whereNull('deletedAt')->where('isActive', true))
            ->when($status === 'inactive', fn ($query) => $query->where(
                fn ($inner) => $inner->whereNotNull('deletedAt')->orWhere('isActive', false),
            ))
            ->when($status === 'blocked', fn ($query) => $query->whereHas(
                'security',
                fn ($inner) => $inner->where('isBlocked', true),
            ))
            ->orderBy('fullName')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(User $user, array $data, ?User $actor): User
    {
        $newRole = isset($data['roleName']) ? mb_strtoupper(trim((string) $data['roleName'])) : null;

        if ($newRole !== null && $newRole !== $user->roleName()) {
            $this->assertCanAssignRole($newRole, $user, $actor);
            $user->roleId = (int) Role::query()->where('roleName', $newRole)->value('id');
        }

        if (array_key_exists('isActive', $data)) {
            $active = (bool) $data['isActive'];

            if (!$active) {
                $this->assertCanDeactivate($user, $actor);
            }

            $user->isActive = $active;
            $user->deletedAt = $active ? null : $user->deletedAt;
        }

        if (array_key_exists('fullName', $data)) {
            $user->fullName = trim((string) $data['fullName']);
        }

        if (array_key_exists('email', $data)) {
            $email = mb_strtolower(trim((string) $data['email']));

            if ($email !== $user->email && $this->emailTaken($email, $user)) {
                throw ValidationException::withMessages([
                    'email' => ['El correo electrónico ya está registrado'],
                ]);
            }

            $user->email = $email;
        }

        $user->save();

        return $user->fresh(['role', 'security']);
    }

    /** Baja lógica: la cuenta deja de poder entrar, el historial la conserva. */
    public function deactivate(User $user, ?User $actor): User
    {
        $this->assertCanDeactivate($user, $actor);

        $user->fill([
            'isActive' => false,
            'deletedAt' => Carbon::now(),
        ])->save();

        // Corta la sesión abierta de esa cuenta.
        $this->attempts->userSecurity((int) $user->id)->fill(['sessionToken' => null])->save();

        return $user->fresh(['role', 'security']);
    }

    public function restore(User $user, ?User $actor): User
    {
        if ($user->isActive && !$user->deletedAt) {
            return $user;
        }

        // Al reactivar vuelve a aplicar el límite de cuentas privilegiadas.
        $this->assertCanAssignRole($user->roleName(), $user, $actor);

        $user->fill(['isActive' => true, 'deletedAt' => null])->save();

        return $user->fresh(['role', 'security']);
    }

    /**
     * Genera una contraseña temporal nueva y la envía por correo. Resuelve el
     * caso de la contraseña perdida y el del correo de alta que no llegó.
     *
     * @return bool si el correo salió; sin correo la cuenta quedaría inaccesible
     */
    public function resetPassword(User $user): bool
    {
        $password = $this->passwords->generateCompliantPassword();
        $now = Carbon::now();

        $user->fill([
            'passwordHash' => Hash::make($password),
            'mustChangePassword' => true,
            'passwordChangedAt' => $now,
        ])->save();

        // Una contraseña nueva invalida la sesión abierta.
        $this->attempts->userSecurity((int) $user->id)->fill(['sessionToken' => null])->save();

        return $this->emailSender->send(
            new UserRegisteredMail((string) $user->fullName, (string) $user->email, $password),
            (string) $user->email,
        );
    }

    // ------------------------------------------------------------------ reglas

    private function assertCanAssignRole(string $roleName, User $user, ?User $actor): void
    {
        if (!in_array($roleName, Role::ALL, true)) {
            throw ValidationException::withMessages([
                'roleName' => ['El rol indicado no existe'],
            ]);
        }

        if ($actor && (int) $actor->id === (int) $user->id) {
            throw ValidationException::withMessages([
                'roleName' => ['No puede cambiar su propio rol'],
            ]);
        }

        $privileged = in_array($roleName, Role::NEVER_BLOCKED, true);

        if ($privileged && $actor?->roleName() !== Role::SUPERADMIN) {
            throw ValidationException::withMessages([
                'roleName' => ['Sólo un SUPERADMIN puede asignar el rol '.$roleName],
            ]);
        }

        if (in_array($roleName, Role::SINGLE_ACCOUNT, true) && $this->roleTaken($roleName, $user)) {
            throw ValidationException::withMessages([
                'roleName' => ["Ya existe una cuenta activa con rol {$roleName}; désela de baja antes de asignarlo."],
            ]);
        }
    }

    private function assertCanDeactivate(User $user, ?User $actor): void
    {
        if ($actor && (int) $actor->id === (int) $user->id) {
            throw ValidationException::withMessages([
                'isActive' => ['No puede dar de baja su propia cuenta'],
            ]);
        }

        if ($user->roleName() === Role::SUPERADMIN && $actor?->roleName() !== Role::SUPERADMIN) {
            throw ValidationException::withMessages([
                'isActive' => ['Sólo un SUPERADMIN puede dar de baja la cuenta de superadministrador'],
            ]);
        }
    }

    private function roleTaken(string $roleName, User $ignore): bool
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('roleName', $roleName))
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->where('id', '!=', $ignore->id)
            ->exists();
    }

    private function emailTaken(string $email, User $ignore): bool
    {
        return User::query()->where('email', $email)->where('id', '!=', $ignore->id)->exists();
    }
}
