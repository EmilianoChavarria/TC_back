<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de cambio de una fecha aplicable.
 *
 * `calculatedRate` es el tipo de cambio del proceso diario: la publicación de
 * Banxico tal cual, sin operaciones. `factorValue` acompaña al registro como
 * dato informativo del rango, no se aplica al tipo de cambio. `manualRate` es
 * la corrección del usuario sobre la publicación y, cuando existe, es el
 * vigente (`effectiveRate`).
 */
class ExchangeRate extends Model
{
    use HasPublicUuid, Auditable;

    public const SOURCE_AUTOMATIC = 'automatic';
    public const SOURCE_MANUAL = 'manual';

    /**
     * Día feriado: no hay publicación aplicable y el vigente es el del día
     * hábil anterior, cuya fecha queda en `carriedFromDate`.
     */
    public const SOURCE_CARRIED = 'carried';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'exchangerates';

    protected $hidden = ['id', 'factorId', 'manualSetByUserId'];

    protected $fillable = [
        'applicableDate',
        'publishedRate',
        'publishedDate',
        'carriedFromDate',
        'factorId',
        'factorCode',
        'factorValue',
        'calculatedRate',
        'manualRate',
        'effectiveRate',
        'source',
        'manualReason',
        'manualSetByUserId',
        'manualSetAt',
        'deletedAt',
    ];

    protected $casts = [
        'applicableDate' => 'date',
        'publishedDate' => 'date',
        'carriedFromDate' => 'date',
        'publishedRate' => 'decimal:6',
        'factorCode' => 'integer',
        'factorValue' => 'decimal:6',
        'calculatedRate' => 'decimal:6',
        'manualRate' => 'decimal:6',
        'effectiveRate' => 'decimal:6',
        'notifiedRate' => 'decimal:6',
        'notifiedAt' => 'datetime',
        'manualSetAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    public function factor()
    {
        return $this->belongsTo(ExchangeRateFactor::class, 'factorId');
    }

    public function manualSetBy()
    {
        return $this->belongsTo(User::class, 'manualSetByUserId');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deletedAt');
    }

    public function isManual(): bool
    {
        return $this->manualRate !== null;
    }

    public function isCarried(): bool
    {
        return $this->source === self::SOURCE_CARRIED;
    }

    public function auditLabel(): ?string
    {
        return $this->applicableDate?->toDateString();
    }
}
