<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockedips', function (Blueprint $table) {
            $table->string('ipAddress', 45)->primary();
            $table->string('country', 100)->nullable();
            $table->unsignedInteger('failedAttempts')->default(0);
            $table->timestamp('lastFailedAt')->nullable();
            $table->boolean('isBlockedPermanently')->default(false);
            $table->timestamp('blockedAt')->nullable();
            $table->timestamp('releasedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockedips');
    }
};
