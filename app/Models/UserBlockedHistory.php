<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class UserBlockedHistory extends Model
{
    use HasPublicUuid;

    protected $table = 'userblockedhistory';

    public $timestamps = false;

    protected $hidden = ['id'];

    protected $fillable = [
        'userId',
        'action',
        'reason',
        'failedAttempts',
        'ipAddress',
        'adminUserId',
        'createdAt',
    ];

    protected $casts = [
        'failedAttempts' => 'integer',
        'createdAt' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'adminUserId');
    }
}
