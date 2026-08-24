<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasPublicUuid;

    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const SOFT_DELETED = 'softDeleted';
    public const RESTORED = 'restored';
    public const DELETED = 'deleted';

    /** Etiquetas de la línea de tiempo, como se muestran en pantalla. */
    public const LABELS = [
        self::CREATED => 'Alta',
        self::UPDATED => 'Actualización',
        self::SOFT_DELETED => 'Eliminación lógica',
        self::RESTORED => 'Restauración',
        self::DELETED => 'Eliminación',
    ];

    protected $table = 'auditlogs';
    public $timestamps = false;

    protected $hidden = ['id', 'requestLogId', 'auditableId', 'userId'];

    protected $fillable = [
        'requestLogId',
        'event',
        'auditableTable',
        'auditableId',
        'auditableUuid',
        'recordLabel',
        'oldValues',
        'newValues',
        'changedColumns',
        'recordCreatedAt',
        'recordUpdatedAt',
        'userId',
        'actorName',
        'actorRole',
        'ipAddress',
        'createdAt',
    ];

    protected $casts = [
        'oldValues' => 'array',
        'newValues' => 'array',
        'changedColumns' => 'array',
        'recordCreatedAt' => 'datetime',
        'recordUpdatedAt' => 'datetime',
        'createdAt' => 'datetime',
    ];

    public function requestLog()
    {
        return $this->belongsTo(RequestLog::class, 'requestLogId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /** Texto legible del evento; los eventos propios del dominio se muestran tal cual. */
    public function eventLabel(): string
    {
        return self::LABELS[$this->event] ?? $this->event;
    }

    public function scopeForRecord($query, string $table, ?string $uuid = null)
    {
        $query->where('auditableTable', $table);

        return $uuid ? $query->where('auditableUuid', $uuid) : $query;
    }
}
