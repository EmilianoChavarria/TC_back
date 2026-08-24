<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditRecorder;

/**
 * Registra en `auditlogs` las altas, cambios y bajas del modelo.
 *
 * El modelo puede afinar el registro con:
 *  - auditLabel():   texto legible del registro para la línea de tiempo.
 *  - $auditExcluded: columnas que no deben entrar al diff.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(AuditRecorder::class)->created($model);
        });

        static::updated(function ($model) {
            app(AuditRecorder::class)->updated($model);
        });

        static::deleted(function ($model) {
            // Con SoftDeletes el borrado real se distingue del lógico, que llega
            // como update sobre deletedAt.
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            app(AuditRecorder::class)->deleted($model);
        });
    }

    /** Texto legible del registro; null deja la línea de tiempo sin subtítulo. */
    public function auditLabel(): ?string
    {
        foreach (['fullName', 'name', 'roleName', 'email', 'ipAddress'] as $column) {
            $value = $this->getAttribute($column);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @return array<int, string> columnas fuera del diff, además de config('audit.ignored_columns') */
    public function auditExclude(): array
    {
        return property_exists($this, 'auditExcluded') ? (array) $this->auditExcluded : [];
    }
}
