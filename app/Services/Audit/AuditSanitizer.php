<?php

namespace App\Services\Audit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Deja los datos listos para almacenarse: enmascara lo sensible, descarta
 * identificadores internos y recorta payloads desproporcionados.
 */
class AuditSanitizer
{
    /**
     * Claves que nunca se almacenan por ser llaves primarias o foráneas
     * internas: `id` y cualquier `algoId` (roleId, userId, factorId…).
     * Los identificadores públicos (`uuid`) sí se conservan.
     */
    private const INTERNAL_KEY_PATTERN = '/^id$|Id$/';

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function clean(array $data): array
    {
        return $this->walk($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function payload(array $data): ?array
    {
        $clean = $this->clean($data);

        if ($clean === []) {
            return null;
        }

        $max = (int) config('audit.max_payload_chars', 20000);
        $encoded = json_encode($clean, JSON_UNESCAPED_UNICODE);

        if ($encoded !== false && mb_strlen($encoded) > $max) {
            Log::info('[Audit] payload recortado por tamaño', ['chars' => mb_strlen($encoded)]);

            return ['_truncated' => true, '_chars' => mb_strlen($encoded), '_keys' => array_keys($clean)];
        }

        return $clean;
    }

    public function isRedacted(string $key): bool
    {
        $redacted = array_map('mb_strtolower', (array) config('audit.redacted', []));

        return in_array(mb_strtolower($key), $redacted, true);
    }

    public function isInternalKey(string $key): bool
    {
        return preg_match(self::INTERNAL_KEY_PATTERN, $key) === 1;
    }

    public function isIgnoredColumn(string $key, array $extra = []): bool
    {
        $ignored = array_merge((array) config('audit.ignored_columns', []), $extra);

        return in_array($key, $ignored, true);
    }

    private function walk(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return ['_file' => $value->getClientOriginalName(), '_bytes' => $value->getSize()];
        }

        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        }

        if (!is_array($value)) {
            return $value;
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                if ($this->isInternalKey($key)) {
                    continue;
                }

                if ($this->isRedacted($key)) {
                    $result[$key] = config('audit.redaction_mask');

                    continue;
                }
            }

            $result[$key] = $this->walk($item);
        }

        return $result;
    }
}
