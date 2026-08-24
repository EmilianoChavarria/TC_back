<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de cambio de una fecha aplicable.
 *
 * `calculatedRate` es lo que produjo el proceso diario (publicación × factor) y
 * nunca se sobrescribe con capturas manuales. `manualRate` es la corrección del
 * usuario. El vigente (`effectiveRate`) es el manual cuando existe.
 */
class ExchangeRate extends Model
{
    use HasPublicUuid, Auditable;

    public const SOURCE_AUTOMATIC = 'automatic';
    public const SOURCE_MANUAL = 'manual';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'exchangerates';

    protected $hidden = ['id', 'factorId', 'manualSetByUserId'];

    protected $fillable = [
        'applicableDate',
        'publishedRate',
        'publishedDate',
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
        'publishedRate' => 'decimal:6',
        'factorCode' => 'integer',
        'factorValue' => 'decimal:6',
        'calculatedRate' => 'decimal:6',
        'manualRate' => 'decimal:6',
        'effectiveRate' => 'decimal:6',
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

    public function auditLabel(): ?string
    {
        return $this->applicableDate?->toDateString();
    }
}
