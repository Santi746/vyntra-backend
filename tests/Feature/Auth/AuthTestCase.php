<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Base para todos los tests de la capa de autenticación.
 *
 * Técnica clave: las rutas protegidas exigen ability '*', así que los
 * tests crean un token Sanctum con ['*'] y lo envían como Bearer.
 * (Los tests existentes usan actingAs sin token y por eso caen en 401/403.)
 */
abstract class AuthTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function token(User $user, array $abilities = ['*']): string
    {
        return $user->createToken('test', $abilities)->plainTextToken;
    }

    protected function authHeaders(User $user, array $abilities = ['*']): array
    {
        return ['Authorization' => 'Bearer '.$this->token($user, $abilities)];
    }

    protected function totp(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }
}
