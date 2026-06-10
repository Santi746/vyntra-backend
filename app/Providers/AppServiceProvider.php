<?php

namespace App\Providers;

use App\Models\ChannelMessage;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\ClubChannel;
use App\Models\ClubMember;
use App\Models\ClubMemberRole;
use App\Models\DmConversation;
use App\Models\Notification;
use App\Policies\ChannelMessagePolicy;
use App\Policies\ClubCategoryPolicy;
use App\Policies\ClubChannelPolicy;
use App\Policies\ClubMemberPolicy;
use App\Policies\ClubMemberRolePolicy;
use App\Policies\ClubPolicy;
use App\Policies\DmConversationPolicy;
use App\Policies\NotificationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Proveedor de servicios principal de la aplicación.
 *
 * Registra servicios y configuraciones globales en el
 * contenedor de Laravel. Aquí se cargan migraciones desde
 * subcarpetas y se registran bindings personalizados.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra servicios en el contenedor de la aplicación.
     */
    public function register(): void
    {
        //
    }

    /**
     * Inicializa servicios después de que todos los proveedores
     * hayan sido registrados.
     *
     * Carga migraciones desde subcarpetas para organizar
     * las tablas por módulo (auth, clubs, social, chat).
     */
    public function boot(): void
    {
        Gate::policy(Club::class, ClubPolicy::class);
        Gate::policy(ChannelMessage::class, ChannelMessagePolicy::class);
        Gate::policy(ClubCategory::class, ClubCategoryPolicy::class);
        Gate::policy(ClubChannel::class, ClubChannelPolicy::class);
        Gate::policy(ClubMember::class, ClubMemberPolicy::class);
        Gate::policy(ClubMemberRole::class, ClubMemberRolePolicy::class);
        Gate::policy(DmConversation::class, DmConversationPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);

        // Cargar migraciones desde subcarpetas recursivamente
        if ($this->app->runningInConsole()) {
            $mainPath = database_path('migrations');
            $directories = glob($mainPath . '/*', GLOB_ONLYDIR);
            $paths = array_merge([$mainPath], $directories);

            $this->loadMigrationsFrom($paths);
        }
    }
}
