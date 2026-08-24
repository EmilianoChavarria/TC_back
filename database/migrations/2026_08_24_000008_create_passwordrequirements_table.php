<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passwordrequirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('minLength')->default(10);
            $table->boolean('requireUppercase')->default(true);
            $table->boolean('requireLowercase')->default(true);
            $table->boolean('requireNumbers')->default(true);
            $table->boolean('requireSpecialChars')->default(true);
            $table->string('allowedSpecialChars', 255)->default('!#$%&*?');
            $table->unsignedInteger('expirationDays')->default(90);
            $table->unsignedBigInteger('updatedByUserId')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('updatedByUserId')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passwordrequirements');
    }
};
