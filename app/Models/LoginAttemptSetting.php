<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LoginAttemptSetting extends Model
{
    use Auditable;

    protected $table = 'loginattemptsettings';
    public $timestamps = false;

    protected $fillable = [
        'maxUserAttempts',
        'maxIpAttempts',
        'sessionTimeoutMinutes',
        'updatedByUserId',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'maxUserAttempts' => 'integer',
        'maxIpAttempts' => 'integer',
        'sessionTimeoutMinutes' => 'integer',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updatedByUserId');
    }
}
