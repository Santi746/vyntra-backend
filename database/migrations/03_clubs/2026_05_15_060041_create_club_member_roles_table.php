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
        Schema::create('club_member_roles', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('club_member_uuid')->constrained('club_members', 'uuid');
            $table->foreignUuid('role_uuid')->constrained('club_roles', 'uuid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_member_roles');
    }
};
