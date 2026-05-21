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
        Schema::create('dm_conversations', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            // Relaciones con los dos participantes del chat privado
            $table->foreignUuid('user_one_uuid')->constrained('users', 'uuid');
            $table->foreignUuid('user_two_uuid')->constrained('users', 'uuid');

            $table->timestamps();
            $table->softDeletes();

            // REGLA DE ORO DE INTEGRIDAD: Evitar múltiples chats privados entre los mismos dos usuarios
            // Nota de Arquitectura: El Backend SIEMPRE debe ordenar alfabéticamente los UUIDs
            // antes de guardarlos (user_one_uuid < user_two_uuid). Así esta restricción UNIQUE
            // protege al 100% contra duplicados síncronos en race conditions.
            $table->unique(['user_one_uuid', 'user_two_uuid']);

            // ÍNDICES DE ESCALABILIDAD: Optimizar la carga de la bandeja de entrada (inbox)
            // Cuando un usuario pide su lista de chats ordenados del más reciente al más antiguo:
            $table->index(['user_one_uuid', 'updated_at']);
            $table->index(['user_two_uuid', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dm_conversations');
    }
};
