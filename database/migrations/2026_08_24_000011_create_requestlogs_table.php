<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una fila por solicitud interceptada: qué se pidió, quién lo pidió y cuándo.
 * El detalle de los registros afectados vive en `auditlogs`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requestlogs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->string('routeName', 120)->nullable();
            $table->unsignedSmallInteger('statusCode')->nullable();
            $table->unsignedInteger('durationMs')->nullable();

            // Autor: FK interna + copia del nombre y rol para que el historial
            // siga siendo legible aunque la cuenta se elimine después.
            $table->unsignedBigInteger('userId')->nullable();
            $table->string('actorName', 255)->nullable();
            $table->string('actorRole', 50)->nullable();

            $table->string('ipAddress', 45)->nullable();
            $table->string('userAgent', 255)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('createdAt')->nullable();

            $table->foreign('userId')->references('id')->on('users')->nullOnDelete();
            $table->index(['createdAt']);
            $table->index(['userId', 'createdAt']);
            $table->index(['path', 'createdAt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requestlogs');
    }
};
