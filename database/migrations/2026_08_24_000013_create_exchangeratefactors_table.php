<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Factores por rango de la publicación de Banxico.
 * El límite inferior es inclusivo y el superior exclusivo: [rangeFrom, rangeTo).
 * Los rangos vigentes no pueden traslaparse (se valida en el servicio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchangeratefactors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('code')->comment('Clave visible del factor');
            $table->decimal('rangeFrom', 18, 6);
            $table->decimal('rangeTo', 18, 6);
            $table->decimal('factor', 12, 6);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->index('code');
            $table->index(['rangeFrom', 'rangeTo']);
            $table->index('deletedAt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchangeratefactors');
    }
};
