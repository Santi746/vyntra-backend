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
        Schema::create('dm_messages', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            // Relaciones
            $table->foreignUuid('dm_conversation_uuid')->constrained('dm_conversations', 'uuid');
            $table->foreignUuid('sender_uuid')->constrained('users', 'uuid');
            // Relación recursiva (para responder mensajes en DMs)
            $table->uuid('parent_message_uuid')->nullable();
            $table->text('content');
            $table->string('status')->default('sent'); // 'sent', 'edited', etc.

            // Idempotencia: Bloquea mensajes clonados enviados al mismo tiempo por fallas de lag
            $table->uuid('client_uuid')->unique();

            $table->timestamps();
            $table->softDeletes();

            // ÍNDICES DE ESCALABILIDAD (Paginación por Cursor - Carga Inversa de Chat)
            // 1. Cargar el chat privado ordenado del más nuevo al más antiguo (Búsqueda ultra frecuente)
            $table->index(['dm_conversation_uuid', 'created_at']);

            // 2. Buscar respuestas a mensajes específicos (Hilos en DM)
            $table->index('parent_message_uuid');

            // 3. Buscar mensajes por remitente
            $table->index('sender_uuid');
        });

        // Hilos recursivos en DMs: añadir FK después de crear la tabla para evitar el error 42830 de Postgres
        Schema::table('dm_messages', function (Blueprint $table) {
            $table->foreign('parent_message_uuid')->references('uuid')->on('dm_messages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dm_messages');
    }
};
