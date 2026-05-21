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
        Schema::create('club_channels', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            // Relación con Categorías (las categorías agrupan canales)
            $table->foreignUuid('category_uuid')->constrained('club_categories', 'uuid');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('text'); // 'text' o 'voice'
            $table->integer('sort_order')->default(0);
            $table->boolean('is_private')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Índice compuesto para mantener la paginación ordenada por UI
            $table->index(['category_uuid', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_channels');
    }
};
