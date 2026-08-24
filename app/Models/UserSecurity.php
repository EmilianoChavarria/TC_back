<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSecurity extends Model
{
    protected $table = 'usersecurity';
    protected $primaryKey = 'userId';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'userId',
        'sessionToken',
        'lastActivityAt',
        'lastKnownIp',
        'failedAttempts',
        'lastFailedAt',
        'lastLoginAt',
        'isBlocked',
        'blockedAt',
        'blockedReason',
    ];

    protected $casts = [
        'failedAttempts' => 'integer',
        'lastActivityAt' => 'datetime',
        'lastFailedAt' => 'datetime',
        'lastLoginAt' => 'datetime',
        'isBlocked' => 'boolean',
        'blockedAt' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
