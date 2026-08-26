<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Factor aplicable a un rango de la publicación de Banxico: [rangeFrom, rangeTo).
 */
class ExchangeRateFactor extends Model
{
    use HasPublicUuid, Auditable;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'exchangeratefactors';

    protected $hidden = ['id', 'updatedByUserId'];

    protected $fillable = [
        'code',
        'rangeFrom',
        'rangeTo',
        'factor',
        'updatedByUserId',
        'deletedAt',
    ];

    protected $casts = [
        'code' => 'integer',
        'rangeFrom' => 'decimal:6',
        'rangeTo' => 'decimal:6',
        'factor' => 'decimal:6',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updatedByUserId');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deletedAt');
    }

    /** Factor cuyo rango contiene el valor: límite inferior inclusivo, superior exclusivo. */
    public function scopeContaining(Builder $query, float|string $rate): Builder
    {
        return $query->active()
            ->where('rangeFrom', '<=', $rate)
            ->where('rangeTo', '>', $rate);
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function auditLabel(): ?string
    {
        return 'Clave '.$this->code;
    }
}
