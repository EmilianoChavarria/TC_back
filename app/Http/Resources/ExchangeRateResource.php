<?php

namespace App\Http\Resources;

use App\Services\Exchange\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Fila de «Gestión de Tipo de Cambio».
 *
 * @mixin \App\Models\ExchangeRate
 */
class ExchangeRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isManual = $this->manualRate !== null;
        $deleted = $this->deletedAt !== null;

        return [
            'uuid' => (string) $this->uuid,
            'applicableDate' => $this->applicableDate?->toDateString(),
            'dayTag' => $this->dayTag(),

            // Publicación de Banxico que originó el cálculo.
            'publishedRate' => $this->publishedRate !== null ? (string) $this->publishedRate : null,
            'publishedDate' => $this->publishedDate?->toDateString(),

            // Factor aplicado, tal como estaba el día del cálculo.
            'factorCode' => $this->factorCode,
            'factorValue' => $this->factorValue !== null ? (string) $this->factorValue : null,
            'factorApplied' => $this->factorValue !== null,

            'calculatedRate' => $this->calculatedRate !== null ? (string) $this->calculatedRate : null,
            'manualRate' => $this->manualRate !== null ? (string) $this->manualRate : null,
            'effectiveRate' => $this->effectiveRate !== null ? (string) $this->effectiveRate : null,

            'source' => $this->source,
            'sourceLabel' => $isManual ? 'Manual prevalece' : 'Automático',

            'manualReason' => $this->manualReason,
            'manualSetAt' => $this->manualSetAt?->toIso8601String(),
            'lastModifiedBy' => $this->lastModifiedBy(),

            'isEditable' => !$deleted && app(ExchangeRateService::class)->isEditable($this->resource),
            'isDeleted' => $deleted,
            'createdAt' => $this->createdAt?->toIso8601String(),
            'updatedAt' => $this->updatedAt?->toIso8601String(),
        ];
    }

    /** «Hoy» / «Siguiente» para las fechas editables. */
    private function dayTag(): ?string
    {
        $date = $this->applicableDate?->toDateString();

        if ($date === null) {
            return null;
        }

        if ($date === Carbon::today()->toDateString()) {
            return 'today';
        }

        return $date > Carbon::today()->toDateString() ? 'next' : null;
    }

    /**
     * Quién dejó el valor vigente: la persona que capturó el manual, o el
     * proceso automático cuando nadie intervino.
     */
    private function lastModifiedBy(): string
    {
        $manualAuthor = $this->manualRate !== null ? $this->manualSetBy?->fullName : null;

        return (string) ($manualAuthor ?: config('audit.system_actor_label'));
    }
}
