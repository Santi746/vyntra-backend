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
        Schema::create('club_categories', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            // Relación con Clubs
            $table->foreignUuid('club_uuid')->constrained('clubs', 'uuid');
            $table->string('name');
            $table->integer('sort_order')->default(0); // Orden en el que se mostrarán las categorías
            $table->boolean('is_private')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Índice compuesto para mantener la paginación ordenada por UI
            $table->index(['club_uuid', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_categories');
    }
};
