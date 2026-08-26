<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El portal envía todo su correo en español, así que la preferencia de idioma
 * por usuario dejó de tener sentido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferredLanguage');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('preferredLanguage', ['es', 'en'])->default('es')->after('passwordHash');
        });
    }
};
