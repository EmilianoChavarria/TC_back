<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arrastre del tipo de cambio en días feriados.
 *
 * En un día feriado no hay publicación aplicable: el tipo de cambio vigente es
 * el del día hábil anterior. Esa fecha se guarda para poder decir de dónde
 * salió el valor —«conserva el TC del 15/09»— en vez de mostrar un registro
 * automático que aparenta una publicación que nunca existió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchangerates', function (Blueprint $table) {
            $table->date('carriedFromDate')->nullable()->after('publishedDate');
        });
    }

    public function down(): void
    {
        Schema::table('exchangerates', function (Blueprint $table) {
            $table->dropColumn('carriedFromDate');
        });
    }
};
