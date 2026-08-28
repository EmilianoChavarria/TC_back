<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Buzón que recibe el aviso diario del tipo de cambio.
 *
 * No es un usuario del portal: son direcciones de terceros —contabilidad, un
 * corporativo, una lista de distribución— que necesitan el dato del día sin
 * tener cuenta ni entrar a nada.
 */
class NotificationRecipient extends Model
{
    use HasPublicUuid, Auditable;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'notificationrecipients';

    protected $hidden = ['id'];

    protected $fillable = [
        'email',
        'name',
        'isActive',
        'deletedAt',
    ];

    protected $casts = [
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    /** Vigentes: los que no se han dado de baja. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deletedAt');
    }

    /**
     * A quién se le escribe hoy.
     *
     * Vigente Y activo: dar de baja borra el registro de la lista, desactivar
     * solo lo silencia. Son cosas distintas y el proceso diario necesita las
     * dos condiciones.
     */
    public function scopeNotifiable(Builder $query): Builder
    {
        return $query->whereNull('deletedAt')->where('isActive', true);
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /** «Contabilidad · pagos@empresa.mx», como se lee en la línea de tiempo. */
    public function auditLabel(): ?string
    {
        return $this->name ? trim($this->name.' · '.$this->email) : $this->email;
    }
}
