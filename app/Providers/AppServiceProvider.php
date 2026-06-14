<?php

namespace App\Providers;

use App\Models\ChannelMessage;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\ClubChannel;
use App\Models\ClubMember;
use App\Models\ClubMemberRole;
use App\Models\ClubRole;
use App\Models\DmConversation;
use App\Models\Notification;
use App\Policies\ChannelMessagePolicy;
use App\Policies\ClubCategoryPolicy;
use App\Policies\ClubChannelPolicy;
use App\Policies\ClubMemberPolicy;
use App\Policies\ClubMemberRolePolicy;
use App\Policies\ClubPolicy;
use App\Policies\ClubRolePolicy;
use App\Policies\DmConversationPolicy;
use App\Policies\NotificationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registra policies del dominio clubs/DMs/notificaciones
 * y carga migraciones modulares desde subcarpetas.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ─── Policies de Clubs ─────────────────────────────────
        Gate::policy(Club::class, ClubPolicy::class);
        Gate::policy(ClubCategory::class, ClubCategoryPolicy::class);
        Gate::policy(ClubChannel::class, ClubChannelPolicy::class);
        Gate::policy(ClubMember::class, ClubMemberPolicy::class);
        Gate::policy(ClubMemberRole::class, ClubMemberRolePolicy::class);
        Gate::policy(ClubRole::class, ClubRolePolicy::class);
        Gate::policy(ChannelMessage::class, ChannelMessagePolicy::class);

        // ─── Policies de DM ────────────────────────────────────
        Gate::policy(DmConversation::class, DmConversationPolicy::class);

        // ─── Policies de Notificaciones ────────────────────────
        Gate::policy(Notification::class, NotificationPolicy::class);

        // Cargar migraciones desde subcarpetas recursivamente
        if ($this->app->runningInConsole()) {
            $mainPath = database_path('migrations');
            $directories = glob($mainPath.'/*', GLOB_ONLYDIR);
            $paths = array_merge([$mainPath], $directories);

            $this->loadMigrationsFrom($paths);
        }
    }
}
