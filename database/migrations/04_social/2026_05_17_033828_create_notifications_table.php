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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            
            // Relación con Usuarios (A quién va dirigida la notificación)
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid'); 
            
            $table->string('type'); // Tipo (ej: 'friend_request', 'club_invite', 'message_reply')
            
            // Usamos tipo JSON para guardar datos adicionales dinámicos (muy flexible y escalable)
            $table->json('data')->nullable(); 
            
            $table->boolean('is_read')->default(false); // Para el check visual y contador
            
            // Idempotencia: Evita disparar notificaciones duplicadas (nullable por si son del sistema)
            $table->uuid('client_uuid')->nullable()->unique(); 
            
            $table->timestamps();
            $table->softDeletes();
 
            // SUPER ÍNDICES DE ESCALABILIDAD (Optimizados para el "Puntito Rojo" del menú)
            // Este índice compuesto cubre: 
            // 1. Cargar las notificaciones ordenadas por tiempo (Paginación)
            // 2. Filtrar solo las no leídas (is_read = false)
            // 3. Contar notificaciones no leídas de forma instantánea en 0.00ms
            $table->index(['user_uuid', 'is_read', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
