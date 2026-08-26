<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de cambio por fecha aplicable.
 *
 * El proceso diario guarda la publicación de Banxico y el factor informativo
 * que le correspondió; el tipo de cambio automático es la publicación tal cual.
 * La captura manual vive en su propia columna: la publicación nunca se pisa, y
 * el vigente es el manual cuando existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchangerates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('applicableDate')->unique();

            // Publicación de Banxico: es el tipo de cambio automático.
            $table->decimal('publishedRate', 18, 6)->nullable();
            $table->date('publishedDate')->nullable();

            // Copia del factor informativo del rango: si el factor cambia
            // después, el histórico conserva el que aplicaba ese día.
            $table->unsignedBigInteger('factorId')->nullable();
            $table->unsignedInteger('factorCode')->nullable();
            $table->decimal('factorValue', 12, 6)->nullable();

            // Tipo de cambio del proceso automático (copia de publishedRate).
            $table->decimal('calculatedRate', 18, 6)->nullable();
            $table->decimal('manualRate', 18, 6)->nullable();
            $table->decimal('effectiveRate', 18, 6)->nullable();

            // automatic | manual
            $table->string('source', 20)->default('automatic');

            $table->string('manualReason', 500)->nullable();
            $table->unsignedBigInteger('manualSetByUserId')->nullable();
            $table->timestamp('manualSetAt')->nullable();

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->foreign('factorId')->references('id')->on('exchangeratefactors')->nullOnDelete();
            $table->foreign('manualSetByUserId')->references('id')->on('users')->nullOnDelete();
            $table->index(['applicableDate', 'deletedAt']);
            $table->index('publishedDate');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchangerates');
    }
};
