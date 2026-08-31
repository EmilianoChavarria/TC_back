<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora del proceso de sincronización con Banxico: una fila por corrida,
 * haya terminado bien o mal.
 *
 * La línea de tiempo de auditoría ya guarda los eventos `exchangeRate.sync` y
 * `exchangeRate.syncFailed`, pero esos son un renglón más entre todos los
 * cambios del sistema. Esta tabla existe para poder consultar el proceso por sí
 * solo: cuánto tardó, qué disparó la corrida y cuál fue el error, sin filtrar
 * la bitácora general.
 *
 * No lleva el trait `Auditable`: es una bitácora, auditar la bitácora sólo
 * duplicaría el mismo hecho en dos tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchangeratesyncruns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // `scheduled` (programación diaria), `manual` (panel), `console`
            // (invocación suelta desde la terminal).
            $table->string('trigger', 20);
            $table->string('status', 20);

            // Parámetros con los que corrió, para poder reproducirla igual.
            $table->date('referenceDate')->nullable();
            $table->unsignedSmallInteger('lookbackDays')->nullable();

            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('carried')->default(0);

            // Detalle de la corrida. Son fechas, no identificadores: nada que
            // enumerar desde afuera.
            $table->json('dates')->nullable();
            $table->json('carriedDates')->nullable();

            $table->text('errorMessage')->nullable();

            // Autor: FK interna + copia del nombre, igual que en `requestlogs`,
            // para que la bitácora siga siendo legible si la cuenta se elimina.
            // Nulo cuando la corrida nace de la programación.
            $table->unsignedBigInteger('userId')->nullable();
            $table->string('actorName', 255)->nullable();

            $table->timestamp('startedAt');
            $table->timestamp('finishedAt')->nullable();
            $table->unsignedInteger('durationMs')->nullable();

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('userId')->references('id')->on('users')->nullOnDelete();
            $table->index(['startedAt']);
            $table->index(['status', 'startedAt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchangeratesyncruns');
    }
};
