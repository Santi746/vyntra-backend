<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega un unique constraint compuesto en (provider, provider_id).
     *
     * Esto previene la creación de usuarios duplicados cuando dos
     * callbacks de OAuth (Google/GitHub/Discord) llegan casi al mismo
     * tiempo para el mismo usuario nuevo (race condition).
     *
     * La DB garantiza la integridad a nivel de fila; el SocialiteController
     * captura la excepción y re-lee el registro ya creado por el ganador.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_id']);
        });
    }
};
