<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La pantalla de factores muestra quién aplicó la última modificación de cada
 * rango. Derivarlo de la auditoría obligaría a recorrerla en cada consulta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchangeratefactors', function (Blueprint $table) {
            $table->unsignedBigInteger('updatedByUserId')->nullable()->after('factor');
            $table->foreign('updatedByUserId')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exchangeratefactors', function (Blueprint $table) {
            $table->dropForeign(['updatedByUserId']);
            $table->dropColumn('updatedByUserId');
        });
    }
};
