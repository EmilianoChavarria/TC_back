<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usersecurity', function (Blueprint $table) {
            $table->unsignedBigInteger('userId')->primary();
            $table->text('sessionToken')->nullable();
            $table->timestamp('lastActivityAt')->nullable();
            $table->string('lastKnownIp', 45)->nullable();
            $table->unsignedInteger('failedAttempts')->default(0);
            $table->timestamp('lastFailedAt')->nullable();
            $table->timestamp('lastLoginAt')->nullable();
            $table->boolean('isBlocked')->default(false);
            $table->timestamp('blockedAt')->nullable();
            $table->string('blockedReason', 255)->nullable();

            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usersecurity');
    }
};
