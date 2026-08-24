<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Identificador público del modelo.
 *
 * La llave primaria sigue siendo el BIGINT autoincremental (joins y FK internas);
 * el `uuid` es lo único que sale hacia el cliente. Al sobrescribir uniqueIds()
 * con la columna `uuid`, el trait de Laravel la rellena al crear el registro sin
 * convertir la PK en cadena.
 */
trait HasPublicUuid
{
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** Búsqueda por identificador público. */
    public function scopeWhereUuid($query, string $uuid)
    {
        return $query->where($this->getTable().'.uuid', $uuid);
    }
}
