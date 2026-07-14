<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            // Índice compuesto para descubrimiento de clubs por categoría:
            // WHERE category_tag = ? ORDER BY created_at DESC
            // Permite paginación por cursor sin escanear toda la tabla.
            $table->index(['category_tag', 'created_at'], 'idx_clubs_category_tag_created');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropIndex('idx_clubs_category_tag_created');
        });
    }
};
