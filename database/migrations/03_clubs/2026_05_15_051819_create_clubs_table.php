<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('avatar_url')->nullable();
            $table->foreignUuid('owner_uuid')->constrained('users', 'uuid');
            $table->index('owner_uuid'); // Índice esencial para PostgreSQL
            $table->string('category_tag')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clubs');
    }
};
