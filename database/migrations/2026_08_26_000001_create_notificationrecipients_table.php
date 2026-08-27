<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Destinatarios del aviso de tipo de cambio.
 *
 * No son usuarios del portal y por eso viven en su propia tabla: son buzones
 * de terceros —contabilidad, un corporativo, una lista de distribución— que
 * necesitan el dato del día sin tener cuenta ni entrar a nada. Colgarlos de
 * `users` obligaría a crear accesos que nadie va a usar y a explicar por qué
 * hay usuarios que no pueden iniciar sesión.
 *
 * El correo no lleva índice único porque la baja es lógica: una dirección
 * eliminada puede volver a darse de alta. La unicidad entre los vigentes la
 * valida NotificationRecipientService, igual que en holidays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificationrecipients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('email', 190);
            $table->string('name', 150)->nullable();

            // Permite silenciar un buzón sin perder el registro de que existía
            // ni quién lo dio de alta. Borrar y volver a crear rompe el rastro.
            $table->boolean('isActive')->default(true);

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            // La consulta del proceso diario: vigentes y activos.
            $table->index(['deletedAt', 'isActive']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificationrecipients');
    }
};
