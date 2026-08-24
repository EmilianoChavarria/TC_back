<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipblockedhistory', function (Blueprint $table) {
            $table->id();
            $table->string('ipAddress', 45);
            $table->enum('action', ['blocked', 'unblocked']);
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('failedAttempts')->default(0);
            $table->unsignedBigInteger('userId')->nullable();
            $table->unsignedBigInteger('adminUserId')->nullable();
            $table->timestamp('createdAt')->nullable();

            $table->foreign('adminUserId')->references('id')->on('users')->nullOnDelete();
            $table->index(['ipAddress', 'createdAt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipblockedhistory');
    }
};
