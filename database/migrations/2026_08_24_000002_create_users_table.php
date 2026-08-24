<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('fullName', 255);
            $table->string('email', 150)->unique();
            $table->string('passwordHash', 255);
            $table->foreignId('roleId')->constrained('roles')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('preferredLanguage', ['es', 'en'])->default('es');
            $table->boolean('isActive')->default(true);
            $table->boolean('mustChangePassword')->default(true);
            $table->timestamp('passwordChangedAt')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->index('isActive');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
