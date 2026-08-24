<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class EmailConfig extends Model
{
    use Auditable;

    public const MODE_NORMAL = 'normal';
    public const MODE_OVERRIDE = 'override';
    public const MODE_DISABLED = 'disabled';

    protected $table = 'emailconfig';
    public $timestamps = false;

    protected $fillable = [
        'emailSupport',
        'emailMode',
        'overrideEmail',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];
}
