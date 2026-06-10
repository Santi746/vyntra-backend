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
        Schema::create('channel_messages', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            
            // Relaciones
            $table->foreignUuid('club_channel_uuid')->constrained('club_channels', 'uuid');
            $table->foreignUuid('sender_uuid')->constrained('users', 'uuid');
            
            // Relación recursiva (para respuestas/hilos de chat). Apunta a esta misma tabla.
            // Definimos la columna y su índice primero; el constraint de FK se añade en un bloque posterior
            // para asegurar que la PK ('uuid') ya exista físicamente en PostgreSQL.
            $table->uuid('parent_message_uuid')->nullable();
                  
            $table->text('content');
            $table->string('status')->default('sent'); // 'sent', 'edited', etc.
            
            // Protocolo de Idempotencia (Regla de Tiempo Real): Evita mensajes duplicados por lag
            // NOTA: UNIQUE compuesto con club_channel_uuid para que dos canales no compartan el mismo espacio de client_uuids
            $table->uuid('client_uuid');
            $table->unique(['club_channel_uuid', 'client_uuid']);
            
            $table->timestamps();
            $table->softDeletes();

            // Índices de Escalabilidad y Paginación por Cursor
            // 1. Cargar el chat de un canal ordenado en reversa por tiempo (Ultra frecuente)
            $table->index(['club_channel_uuid', 'created_at']);
            
            // 2. Buscar todas las respuestas a un mensaje específico (Hilos)
            $table->index('parent_message_uuid');
            
            // 3. Buscar mensajes por remitente
            $table->index('sender_uuid');
        });

        // Hilos recursivos: añadir FK después de crear la tabla para evitar el error 42830 de Postgres
        Schema::table('channel_messages', function (Blueprint $table) {
            $table->foreign('parent_message_uuid')
                  ->references('uuid')
                  ->on('channel_messages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_messages');
    }
};
