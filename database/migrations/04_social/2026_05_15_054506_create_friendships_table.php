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
        Schema::create('friendships', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('sender_uuid')->constrained('users', 'uuid');
            $table->foreignUuid('receiver_uuid')->constrained('users', 'uuid');
            $table->string('status')->default('pending');
            // client_uuid para deduplicación de solicitudes en tiempo real (evita solicitudes duplicadas por retry de WebSocket)
            $table->uuid('client_uuid')->nullable();
            $table->unique(['sender_uuid', 'client_uuid']);
            $table->timestamps();
            $table->softDeletes();

            // Evitar múltiples solicitudes del mismo emisor al mismo receptor
            $table->unique(['sender_uuid', 'receiver_uuid']);

            // Índice compuesto para búsquedas por receptor ordenadas por fecha (paginación cursor)
            $table->index(['receiver_uuid', 'created_at']);

            // También útil para ver a quién le enviaste solicitudes recientemente
            $table->index(['sender_uuid', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
