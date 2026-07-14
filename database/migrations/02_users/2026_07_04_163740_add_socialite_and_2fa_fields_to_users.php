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
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('is_online');
            $table->string('provider_id')->nullable()->after('provider');
            $table->text('provider_token')->nullable()->after('provider_id');
            $table->text('provider_refresh_token')->nullable()->after('provider_token');

            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_confirmed_at');
            $table->json('two_factor_backup_codes')->nullable()->after('two_factor_enabled');

            $table->index(['provider', 'provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['provider', 'provider_id']);
            $table->dropColumn([
                'provider',
                'provider_id',
                'provider_token',
                'provider_refresh_token',
                'two_factor_enabled',
                'two_factor_backup_codes',
            ]);
        });
    }
};
