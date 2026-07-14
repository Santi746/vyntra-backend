<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Database\UniqueConstraintViolationException;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * Controlador de autenticación social (OAuth2).
 *
 * Providers soportados: google, github, discord.
 * El flujo es:
 *   1. redirect()  → redirige al usuario al proveedor
 *   2. callback()  → el proveedor redirige de vuelta con un code
 *   3. Se intercambia el code por datos del usuario
 *   4. Se busca o crea el usuario en la DB
 *   5. Se emite un token Sanctum
 *   6. Se redirige al frontend con el token en la URL
 *   7. Si el user es nuevo, el frontend lo redirige a /auth/complete-profile
 *      para que complete username, first_name, last_name
 */
class SocialiteController extends Controller
{
    /**
     * Redirige al usuario al proveedor social.
     *
     * Socialite construye automáticamente la URL de OAuth2
     * con los parámetros correctos (client_id, redirect_uri, scope, state).
     * Laravel responde con un HTTP 302 (redirect).
     *
     * El provider se valida en la ruta con ->where('provider', '...'),
     * por lo que no repetimos la validación acá.
     */
    public function redirect(string $provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Maneja el callback del proveedor social.
     *
     * 1. Socialite intercambia el code por un access_token
     * 2. Busca un usuario existente por provider_id o email
     * 3. Si existe: actualiza datos (avatar, token)
     * 4. Si no existe: crea usuario nuevo con datos del provider
     *    (username, first_name, last_name quedan null hasta que complete-profile)
     * 5. Genera token Sanctum
     * 6. Redirige al frontend con el token
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException $e) {
            // OAuth state inválido o expirado (CSRF protection)
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            return redirect($frontendUrl.'/auth/callback?error=invalid_state');
        }

        $user = $this->findOrCreateSocialUser($provider, $socialUser);

        // Si el usuario tiene 2FA, emitir token pendiente (no bypass)
        if ($user->has2faEnabled()) {
            $pendingToken = $user->createToken('2fa_pending', ['2fa_pending'])->plainTextToken;
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            return redirect($frontendUrl.'/auth/callback?token='.$pendingToken.'&requires_2fa=1');
        }

        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        return redirect($frontendUrl.'/auth/callback?token='.$token);
    }

    /**
     * Busca o crea un usuario a partir de los datos del proveedor social.
     *
     * Orden de búsqueda:
     *   1. provider + provider_id (usuario ya vinculado)
     *   2. email (vincular cuenta existente)
     *   3. Crear nuevo usuario con email, provider, avatar — pero
     *      username, first_name, last_name quedan null. El frontend
     *      debe redirigirlo a /auth/complete-profile para que los complete.
     *
     * @param  string  $provider  'google', 'github', 'discord'
     * @param  \Laravel\Socialite\Two\User  $socialUser  Datos del proveedor
     */
    private function findOrCreateSocialUser(string $provider, $socialUser): User
    {
        // ─── 1. Buscar por provider_id ──────────────────────────
        $user = User::where('provider', $provider)
            ->where('provider_id', (string) $socialUser->id)
            ->first();

        if ($user) {
            $user->update(['provider_token' => $socialUser->token]);

            return $user;
        }

        // ─── 2. Buscar por email (vincular cuenta existente) ─────────
        $user = User::where('email', $socialUser->email)->first();

        if ($user) {
            $user->update([
                'provider' => $provider,
                'provider_id' => (string) $socialUser->id,
                'provider_token' => $socialUser->token,
                'provider_refresh_token' => $socialUser->refreshToken,
                'avatar_url' => $socialUser->avatar ?? $user->avatar_url,
            ]);

            return $user;
        }

        // ─── 3. Crear usuario nuevo ─────────────────────────────────
        // username, first_name, last_name = null. El frontend los pide
        // después en /auth/complete-profile (PATCH /api/user/complete-profile).
        return User::create([
            'username' => null,
            'first_name' => null,
            'last_name' => null,
            'email' => $socialUser->email,
            'password' => Str::random(32), // el user no conoce esta pass. cast 'hashed' del modelo la hashea
            'provider' => $provider,
            'provider_id' => (string) $socialUser->id,
            'provider_token' => $socialUser->token,
            'avatar_url' => $socialUser->avatar,
            'email_verified_at' => now(), // OAuth = email verificado por el provider
        ]);
    }
}
