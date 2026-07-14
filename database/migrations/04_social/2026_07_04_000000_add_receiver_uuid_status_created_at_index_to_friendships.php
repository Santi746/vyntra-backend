<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('friendships', function (Blueprint $table) {
            // Índice compuesto para la query más frecuente en el inbox social:
            // "Mostrar solicitudes de amistad PENDIENTES que recibí, ordenadas por fecha"
            // WHERE receiver_uuid = ? AND status = 'pending' ORDER BY created_at DESC
            $table->index(['receiver_uuid', 'status', 'created_at'], 'idx_friendships_receiver_status_created');
        });
    }

    public function down(): void
    {
        Schema::table('friendships', function (Blueprint $table) {
            $table->dropIndex('idx_friendships_receiver_status_created');
        });
    }
};
