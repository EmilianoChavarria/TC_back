<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasPublicUuid, Auditable;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'holidays';

    protected $hidden = ['id'];

    protected $fillable = [
        'holidayDate',
        'description',
        'deletedAt',
    ];

    protected $casts = [
        'holidayDate' => 'date',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deletedAt');
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->whereBetween('holidayDate', ["{$year}-01-01", "{$year}-12-31"]);
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function year(): int
    {
        return (int) $this->holidayDate->year;
    }

    /** «Viernes Santo · 2026-04-03», como se lee en la línea de tiempo. */
    public function auditLabel(): ?string
    {
        return trim($this->description.' · '.$this->holidayDate?->toDateString());
    }
}
