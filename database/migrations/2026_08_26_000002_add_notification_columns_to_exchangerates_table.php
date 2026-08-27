<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de qué valor se avisó por correo en cada fecha aplicable.
 *
 * ⚠️ Sin esto el aviso se duplica solo. `exchange-rate:sync` está programado
 * DOS veces al día —13:30 y 18:00— y además consulta una ventana de días hacia
 * atrás, así que la misma fecha se reprocesa varias veces con el mismo valor.
 *
 * Se guarda el valor notificado y no un simple booleano a propósito: si alguien
 * corrige el tipo de cambio a mano después del envío, el valor cambia y la
 * lista tiene que enterarse. Con un booleano, esa corrección quedaría muda —
 * que es justo el caso en el que el aviso más importa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchangerates', function (Blueprint $table) {
            $table->decimal('notifiedRate', 18, 6)->nullable()->after('source');
            $table->timestamp('notifiedAt')->nullable()->after('notifiedRate');
        });
    }

    public function down(): void
    {
        Schema::table('exchangerates', function (Blueprint $table) {
            $table->dropColumn(['notifiedRate', 'notifiedAt']);
        });
    }
};
