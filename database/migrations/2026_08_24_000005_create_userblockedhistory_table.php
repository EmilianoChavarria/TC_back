<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('userblockedhistory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            $table->enum('action', ['blocked', 'unblocked']);
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('failedAttempts')->default(0);
            $table->string('ipAddress', 45)->nullable();
            $table->unsignedBigInteger('adminUserId')->nullable();
            $table->timestamp('createdAt')->nullable();

            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('adminUserId')->references('id')->on('users')->nullOnDelete();
            $table->index(['userId', 'createdAt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('userblockedhistory');
    }
};
