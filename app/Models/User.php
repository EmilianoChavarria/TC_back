<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasPublicUuid, Auditable;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'users';

    protected $fillable = [
        'fullName',
        'email',
        'passwordHash',
        'roleId',
        'preferredLanguage',
        'isActive',
        'mustChangePassword',
        'passwordChangedAt',
        'deletedAt',
    ];

    protected $hidden = [
        'id',
        'passwordHash',
    ];

    protected $casts = [
        'isActive' => 'boolean',
        'mustChangePassword' => 'boolean',
        'passwordChangedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'roleId');
    }

    public function security()
    {
        return $this->hasOne(UserSecurity::class, 'userId');
    }

    public function blockHistory()
    {
        return $this->hasMany(UserBlockedHistory::class, 'userId');
    }

    /**
     * Nombre del rol en mayúsculas. Toma la columna "roleName" cuando viene de un join
     * y, si no, la relación cargada.
     */
    public function roleName(): string
    {
        $raw = $this->attributes['roleName'] ?? $this->role?->roleName;

        return mb_strtoupper(trim((string) $raw));
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleName() === Role::SUPERADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->roleName() === Role::ADMIN;
    }

    public function isSecurityAdmin(): bool
    {
        return in_array($this->roleName(), Role::SECURITY_ADMINS, true);
    }

    /** Cuentas exentas de bloqueo por intentos fallidos (la IP sí se bloquea). */
    public function isBlockExempt(): bool
    {
        return in_array($this->roleName(), Role::NEVER_BLOCKED, true);
    }
}
