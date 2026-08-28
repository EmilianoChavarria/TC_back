<?php

namespace App\Http\Resources;

use App\Services\Exchange\ExchangeRateService;
use App\Support\Decimals;
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
        $isCarried = $this->resource->isCarried();
        $deleted = $this->deletedAt !== null;

        return [
            'uuid' => (string) $this->uuid,
            'applicableDate' => $this->applicableDate?->toDateString(),
            'dayTag' => $this->dayTag(),

            // Publicación de Banxico: el tipo de cambio automático.
            'publishedRate' => Decimals::rate($this->publishedRate),
            'publishedDate' => $this->publishedDate?->toDateString(),

            // Día feriado: de qué fecha se arrastró el tipo de cambio vigente.
            'carriedFromDate' => $this->carriedFromDate?->toDateString(),
            'isCarried' => $isCarried,

            // Factor informativo del rango, tal como estaba ese día.
            'factorCode' => $this->factorCode,
            'factorValue' => Decimals::factor($this->factorValue),
            'factorApplied' => $this->factorValue !== null,

            'calculatedRate' => Decimals::rate($this->calculatedRate),
            'manualRate' => Decimals::rate($this->manualRate),
            'effectiveRate' => Decimals::rate($this->effectiveRate),

            'source' => $this->source,
            'sourceLabel' => $this->sourceLabel($isManual, $isCarried),

            'manualReason' => $this->manualReason,
            'manualSetAt' => $this->manualSetAt?->toIso8601String(),
            'lastModifiedBy' => $this->lastModifiedBy(),
            'lastModifiedAt' => ($this->manualRate !== null ? $this->manualSetAt : $this->updatedAt)?->toIso8601String(),

            'isEditable' => !$deleted && app(ExchangeRateService::class)->isEditable($this->resource),
            'isDeleted' => $deleted,
            'createdAt' => $this->createdAt?->toIso8601String(),
            'updatedAt' => $this->updatedAt?->toIso8601String(),
        ];
    }

    private function sourceLabel(bool $isManual, bool $isCarried): string
    {
        if ($isManual) {
            return 'Manual prevalece';
        }

        // Se dice de dónde viene: un valor idéntico al de ayer sin explicación
        // se lee como que el proceso no corrió.
        return $isCarried
            ? 'Feriado · TC del '.($this->carriedFromDate?->format('d/m/Y') ?? 'día hábil anterior')
            : 'Automático';
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
