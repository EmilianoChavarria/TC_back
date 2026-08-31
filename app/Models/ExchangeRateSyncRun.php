<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una corrida del proceso de sincronización con Banxico.
 *
 * Sin el trait `Auditable` a propósito: esta tabla ya es la bitácora del
 * proceso, auditarla duplicaría el mismo hecho.
 */
class ExchangeRateSyncRun extends Model
{
    use HasPublicUuid;

    public const TRIGGER_SCHEDULED = 'scheduled';
    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_CONSOLE = 'console';

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'exchangeratesyncruns';

    protected $hidden = ['id', 'userId'];

    protected $fillable = [
        'trigger',
        'status',
        'referenceDate',
        'lookbackDays',
        'processed',
        'carried',
        'dates',
        'carriedDates',
        'errorMessage',
        'userId',
        'actorName',
        'startedAt',
        'finishedAt',
        'durationMs',
    ];

    protected $casts = [
        'referenceDate' => 'date',
        'lookbackDays' => 'integer',
        'processed' => 'integer',
        'carried' => 'integer',
        'dates' => 'array',
        'carriedDates' => 'array',
        'durationMs' => 'integer',
        'startedAt' => 'datetime',
        'finishedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('startedAt')->orderByDesc('id');
    }

    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
