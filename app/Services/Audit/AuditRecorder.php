<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Traduce cambios de modelos y eventos de dominio a filas de `auditlogs`.
 */
class AuditRecorder
{
    public function __construct(
        private readonly AuditContext $context,
        private readonly AuditSanitizer $sanitizer,
    ) {
    }

    public function created(Model $model): void
    {
        $this->push($model, AuditLog::CREATED, null, $this->values($model, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $excluded = $this->excludedColumns($model);

        foreach (array_keys($changes) as $column) {
            if ($this->sanitizer->isIgnoredColumn($column, $excluded) || $this->sanitizer->isInternalKey($column)) {
                unset($changes[$column]);
            }
        }

        if ($changes === []) {
            return;
        }

        $original = array_intersect_key($model->getRawOriginal(), $changes);

        $this->push(
            $model,
            $this->resolveUpdateEvent($model, $changes),
            $this->values($model, $original),
            $this->values($model, $changes),
            array_keys($changes),
        );
    }

    public function deleted(Model $model): void
    {
        $this->push($model, AuditLog::DELETED, $this->values($model, $model->getRawOriginal()), null);
    }

    /**
     * Evento propio del dominio, sin cambio de modelo detrás
     * (por ejemplo el envío de un correo o una acción de proceso automático).
     *
     * @param array<string, mixed> $details
     */
    public function event(string $event, string $table, ?string $recordUuid = null, ?string $label = null, array $details = []): void
    {
        $this->context->record([
            'uuid' => (string) Str::uuid7(),
            'event' => $event,
            'auditableTable' => $table,
            'auditableId' => null,
            'auditableUuid' => $recordUuid,
            'recordLabel' => $label,
            'oldValues' => null,
            'newValues' => $details === [] ? null : $this->sanitizer->clean($details),
            'changedColumns' => null,
            'recordCreatedAt' => null,
            'recordUpdatedAt' => null,
        ]);
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     * @param array<int, string>|null   $columns
     */
    private function push(Model $model, string $event, ?array $old, ?array $new, ?array $columns = null): void
    {
        if (!$this->context->isEnabled()) {
            return;
        }

        $this->context->record([
            'uuid' => (string) Str::uuid7(),
            'event' => $event,
            'auditableTable' => $model->getTable(),
            'auditableId' => $this->internalId($model),
            'auditableUuid' => $this->publicUuid($model),
            'recordLabel' => $this->label($model),
            'oldValues' => $old,
            'newValues' => $new,
            'changedColumns' => $columns,
            'recordCreatedAt' => $this->timestamp($model, 'createdAt', $model->getCreatedAtColumn()),
            'recordUpdatedAt' => $this->timestamp($model, 'updatedAt', $model->getUpdatedAtColumn()),
        ]);
    }

    /**
     * Una baja lógica se ve como un update sobre `deletedAt`: se distingue para
     * que la línea de tiempo la muestre como eliminación o restauración.
     */
    private function resolveUpdateEvent(Model $model, array $changes): string
    {
        if (!array_key_exists('deletedAt', $changes)) {
            return AuditLog::UPDATED;
        }

        return $changes['deletedAt'] === null ? AuditLog::RESTORED : AuditLog::SOFT_DELETED;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function values(Model $model, array $attributes): array
    {
        $excluded = $this->excludedColumns($model);

        foreach (array_keys($attributes) as $column) {
            if ($this->sanitizer->isIgnoredColumn($column, $excluded) || $this->sanitizer->isInternalKey($column)) {
                unset($attributes[$column]);
            }
        }

        return $this->sanitizer->clean($attributes);
    }

    /** @return array<int, string> */
    private function excludedColumns(Model $model): array
    {
        return method_exists($model, 'auditExclude') ? $model->auditExclude() : [];
    }

    private function label(Model $model): ?string
    {
        if (method_exists($model, 'auditLabel')) {
            $label = $model->auditLabel();

            return $label === null ? null : Str::limit((string) $label, 250, '');
        }

        return null;
    }

    private function publicUuid(Model $model): ?string
    {
        $uuid = $model->getAttribute('uuid');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    private function internalId(Model $model): ?int
    {
        $key = $model->getKey();

        return is_numeric($key) ? (int) $key : null;
    }

    private function timestamp(Model $model, string $column, ?string $fallbackColumn): ?Carbon
    {
        foreach (array_filter([$column, $fallbackColumn]) as $candidate) {
            $value = $model->getAttribute($candidate);

            if ($value) {
                return Carbon::parse($value);
            }
        }

        return null;
    }
}
