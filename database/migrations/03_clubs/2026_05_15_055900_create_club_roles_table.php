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
        Schema::create('club_roles', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            // Relación con Clubs
            $table->foreignUuid('club_uuid')->constrained('clubs', 'uuid');
            $table->string('name');
            $table->string('color');
            $table->boolean('is_fixed')->default(false);
            $table->unsignedBigInteger('permissions')->default(0);
            $table->integer('sort_order')->default(0);
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            // Índice compuesto para mantener la jerarquía ordenada
            $table->index(['club_uuid', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_roles');
    }
};
