<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loginattemptsettings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('maxUserAttempts')->default(5);
            $table->unsignedInteger('maxIpAttempts')->default(12);
            $table->unsignedInteger('sessionTimeoutMinutes')->default(15);
            $table->unsignedBigInteger('updatedByUserId')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('updatedByUserId')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loginattemptsettings');
    }
};
