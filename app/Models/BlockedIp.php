<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    protected $table = 'blockedips';
    protected $primaryKey = 'ipAddress';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ipAddress',
        'country',
        'failedAttempts',
        'lastFailedAt',
        'isBlockedPermanently',
        'blockedAt',
        'releasedAt',
    ];

    protected $casts = [
        'failedAttempts' => 'integer',
        'lastFailedAt' => 'datetime',
        'isBlockedPermanently' => 'boolean',
        'blockedAt' => 'datetime',
        'releasedAt' => 'datetime',
    ];
}
