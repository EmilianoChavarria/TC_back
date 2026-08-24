<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PasswordRequirement extends Model
{
    use Auditable;

    protected $table = 'passwordrequirements';
    public $timestamps = false;

    protected $fillable = [
        'minLength',
        'requireUppercase',
        'requireLowercase',
        'requireNumbers',
        'requireSpecialChars',
        'allowedSpecialChars',
        'expirationDays',
        'updatedByUserId',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'minLength' => 'integer',
        'requireUppercase' => 'boolean',
        'requireLowercase' => 'boolean',
        'requireNumbers' => 'boolean',
        'requireSpecialChars' => 'boolean',
        'expirationDays' => 'integer',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updatedByUserId');
    }
}
