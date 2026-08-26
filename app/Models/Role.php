<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use Auditable;

    public const SUPERADMIN = 'SUPERADMIN';
    public const ADMIN = 'ADMIN';
    public const USER = 'USER';

    /** Roles válidos del sistema (no dinámicos). */
    public const ALL = [self::SUPERADMIN, self::ADMIN, self::USER];

    /** Roles que nunca se bloquean por intentos fallidos (la IP sí). */
    public const NEVER_BLOCKED = [self::SUPERADMIN, self::ADMIN];

    /** Roles que administran la seguridad del sistema. */
    public const SECURITY_ADMINS = [self::SUPERADMIN, self::ADMIN];

    /**
     * Roles de los que sólo puede existir una cuenta activa.
     * El resto de la organización son usuarios.
     */
    public const SINGLE_ACCOUNT = [self::SUPERADMIN, self::ADMIN];

    protected $table = 'roles';
    public $timestamps = false;

    protected $fillable = [
        'roleName',
        'description',
        'isActive',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'roleId');
    }
}
