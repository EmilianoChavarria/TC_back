<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emailconfig', function (Blueprint $table) {
            $table->id();
            $table->string('emailSupport', 255)->nullable();
            $table->enum('emailMode', ['normal', 'override', 'disabled'])->default('normal');
            $table->string('overrideEmail', 255)->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emailconfig');
    }
};
