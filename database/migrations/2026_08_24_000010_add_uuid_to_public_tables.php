<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Identificador público de las tablas que se referencian desde el cliente.
 *
 * La PK sigue siendo BIGINT autoincremental (joins y FK internas); hacia afuera
 * sólo viaja el uuid. Se usa UUIDv7, ordenado por tiempo, para que el índice
 * único no se fragmente como lo haría un v4 aleatorio.
 */
return new class extends Migration
{
    private const TABLES = ['users', 'userblockedhistory', 'ipblockedhistory'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->nullable()->after('id');
            });

            // Backfill de las filas existentes antes de exigir el índice único.
            DB::table($table)->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid7()]);
                }
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->nullable(false)->change();
                $blueprint->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['uuid']);
                $blueprint->dropColumn('uuid');
            });
        }
    }
};
