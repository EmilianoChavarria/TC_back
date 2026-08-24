<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Días feriados. Alimentan el cálculo del día hábil siguiente del tipo de cambio.
 *
 * La fecha no lleva índice único porque la baja es lógica: una fecha eliminada
 * puede volver a capturarse. La unicidad entre los vigentes se valida en
 * HolidayService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('holidayDate');
            $table->string('description', 150);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->index(['holidayDate', 'deletedAt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
