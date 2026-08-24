<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class IpBlockedHistory extends Model
{
    use HasPublicUuid;

    protected $table = 'ipblockedhistory';

    public $timestamps = false;

    protected $hidden = ['id'];

    protected $fillable = [
        'ipAddress',
        'action',
        'reason',
        'failedAttempts',
        'userId',
        'adminUserId',
        'createdAt',
    ];

    protected $casts = [
        'failedAttempts' => 'integer',
        'createdAt' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'adminUserId');
    }
}
