<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una fila por registro afectado: qué tabla, qué registro, qué cambió y quién.
 * Se enlaza con la solicitud que lo provocó (`requestLogId`), que es nulo
 * cuando el cambio nace de consola o de un proceso automático.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditlogs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('requestLogId')->nullable();

            // created | updated | softDeleted | restored | deleted | y eventos
            // propios del dominio (por ejemplo email.sent)
            $table->string('event', 60);

            $table->string('auditableTable', 64);
            $table->unsignedBigInteger('auditableId')->nullable();
            $table->uuid('auditableUuid')->nullable();
            $table->string('recordLabel', 255)->nullable();

            $table->json('oldValues')->nullable();
            $table->json('newValues')->nullable();
            $table->json('changedColumns')->nullable();

            // Marcas de tiempo del registro afectado al momento del cambio.
            $table->timestamp('recordCreatedAt')->nullable();
            $table->timestamp('recordUpdatedAt')->nullable();

            $table->unsignedBigInteger('userId')->nullable();
            $table->string('actorName', 255)->nullable();
            $table->string('actorRole', 50)->nullable();
            $table->string('ipAddress', 45)->nullable();
            $table->timestamp('createdAt')->nullable();

            $table->foreign('requestLogId')->references('id')->on('requestlogs')->nullOnDelete();
            $table->foreign('userId')->references('id')->on('users')->nullOnDelete();
            $table->index(['auditableTable', 'auditableUuid']);
            $table->index(['auditableTable', 'auditableId']);
            $table->index(['event', 'createdAt']);
            $table->index(['createdAt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditlogs');
    }
};
