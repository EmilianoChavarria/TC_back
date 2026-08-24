<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class RequestLog extends Model
{
    use HasPublicUuid;

    protected $table = 'requestlogs';
    public $timestamps = false;

    protected $hidden = ['id', 'userId'];

    protected $fillable = [
        'method',
        'path',
        'routeName',
        'statusCode',
        'durationMs',
        'userId',
        'actorName',
        'actorRole',
        'ipAddress',
        'userAgent',
        'payload',
        'createdAt',
    ];

    protected $casts = [
        'statusCode' => 'integer',
        'durationMs' => 'integer',
        'payload' => 'array',
        'createdAt' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'requestLogId');
    }
}
