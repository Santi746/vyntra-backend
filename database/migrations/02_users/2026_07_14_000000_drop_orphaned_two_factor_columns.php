<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración.
     * Elimina columnas huérfanas de 2FA: el TOTP se delega a un servicio externo
     * y el modelo User ya no las referencia. Dejarlas es schema drift + superficie
     * de mass-assignment muerta (estaban en #[Fillable]).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
    }
};
