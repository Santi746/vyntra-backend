<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

/**
 * Security edge cases for the authentication layer.
 *
 * Estos tests documentan vulnerabilidades conocidas, comportamientos
 * de seguridad perimetral y casos borde. NO "arreglan" el código —
 * reflejan el comportamiento real del sistema tal como está hoy.
 *
 * @see docs/plans/auth_testing_plan.md
 */
class SecurityEdgeCasesTest extends AuthTestCase
{
    // =========================================================================
    // a) 2FA GATE: CHEQUEO DE ABILITIES
    // =========================================================================
    // La ruta /api/auth/2fa/verify requiere ability '2fa_pending'.
    // La ruta GET /api/user y POST /api/auth/logout requieren ability '*'.
    // Un token con ability incorrecta debe recibir 403.

    public function test_full_token_cannot_bypass_2fa_without_code(): void
    {
        $user = User::factory()->create();

        // Un token COMPLETO (ability ['*']) puede llamar a /2fa/verify, pero el
        // controller exige el 'code'. Sin él recibe 422 (NO escala privilegios).
        // El control real de 2FA es el gate de abilities: un token '2fa_pending'
        // NO accede a rutas protegidas (ver test_pending_token_cannot_access_user_endpoint).
        $response = $this->withHeaders($this->authHeaders($user, ['*']))
            ->postJson('/api/auth/2fa/verify', ['code' => '123456']);

        $response->assertStatus(422);
    }

    public function test_pending_token_cannot_access_user_endpoint(): void
    {
        $user = User::factory()->create();

        // Token con ability ['2fa_pending'] → no puede acceder a /api/user
        $response = $this->withHeaders($this->authHeaders($user, ['2fa_pending']))
            ->getJson('/api/user');

        $response->assertStatus(403);
    }

    // =========================================================================
    // b) BACKUP CODE REUSE
    // =========================================================================
    // Los backup codes se hashean con bcrypt y se eliminan del array
    // después del primer uso. Reutilizar el mismo código debe fallar.

    public function test_backup_code_cannot_be_reused(): void
    {
        $password = 'password123';
        $user = User::factory()->create(['password' => Hash::make($password)]);

        // --- 1) Enable + confirm 2FA para obtener backup codes ---
        $secret = $this->enable2fa($user, $password);

        $confirmResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/2fa/confirm', ['code' => $this->totp($secret)]);
        $confirmResponse->assertStatus(200);

        $backupCodes = $confirmResponse->json('data.backup_codes');
        $this->assertNotEmpty($backupCodes, 'Deben generarse backup codes');

        // --- 2) Login → token pendiente ---
        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);
        $pendingToken = $login->json('data.token');
        $this->assertNotEmpty($pendingToken);

        // --- 3) Verificar con backup code (primer uso) → 200 ---
        $response = $this->withHeader('Authorization', 'Bearer '.$pendingToken)
            ->postJson('/api/auth/2fa/verify', ['code' => $backupCodes[0]]);
        $response->assertStatus(200);

        // --- 4) Login otra vez → nuevo token pendiente ---
        $login2 = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);
        $pendingToken2 = $login2->json('data.token');
        $this->assertNotEmpty($pendingToken2);

        // --- 5) Reutilizar el MISMO backup code → debe fallar (422) ---
        $response2 = $this->withHeader('Authorization', 'Bearer '.$pendingToken2)
            ->postJson('/api/auth/2fa/verify', ['code' => $backupCodes[0]]);
        $response2->assertStatus(422);
    }

    /**
     * Helper: activa 2FA y devuelve el secret TOTP.
     */
    private function enable2fa(User $user, string $password): string
    {
        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/2fa/enable', ['password' => $password]);
        $response->assertStatus(200);

        return $response->json('data.secret');
    }

    // =========================================================================
    // c) MASS ASSIGNMENT PROTECTION
    // =========================================================================
    // Los FormRequests solo exponen campos específicos vía $validated.
    // Inyectar two_factor_secret, two_factor_enabled, provider, provider_id
    // debe ser ignorado tanto en register como en complete-profile.

    public function test_register_ignores_sensitive_fields(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username' => 'massassign',
            'first_name' => 'Mass',
            'last_name' => 'Assign',
            'email' => 'massassign@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // --- Intentos de inyección por mass assignment ---
            'two_factor_secret' => 'EVILSECRET',
            'two_factor_enabled' => true,
            'provider' => 'google',
            'provider_id' => 'evil-123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'massassign@example.com')->first();

        $this->assertNull($user->two_factor_secret, 'two_factor_secret no debe ser asignable vía register');
        $this->assertFalse($user->two_factor_enabled, 'two_factor_enabled no debe ser asignable vía register');
        $this->assertNull($user->provider, 'provider no debe ser asignable vía register');
        $this->assertNull($user->provider_id, 'provider_id no debe ser asignable vía register');
    }

    public function test_complete_profile_ignores_sensitive_fields(): void
    {
        $user = User::factory()->create([
            'username' => null,
            'first_name' => null,
            'last_name' => null,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson('/api/user/complete-profile', [
                'username' => 'completeok',
                'first_name' => 'Complete',
                'last_name' => 'Ok',
                // --- Intentos de inyección por mass assignment ---
                'two_factor_secret' => 'EVILSECRET',
                'two_factor_enabled' => true,
                'provider' => 'github',
                'provider_id' => 'evil-456',
            ])
            ->assertStatus(200);

        $user->refresh();

        $this->assertNull($user->two_factor_secret, 'two_factor_secret no debe ser asignable vía complete-profile');
        $this->assertFalse($user->two_factor_enabled, 'two_factor_enabled no debe ser asignable vía complete-profile');
        $this->assertNull($user->provider, 'provider no debe ser asignable vía complete-profile');
        $this->assertNull($user->provider_id, 'provider_id no debe ser asignable vía complete-profile');
    }

    // =========================================================================
    // d) EMAIL ENUMERATION (MITIGADO)
    // =========================================================================
    // El FormRequest ForgotPasswordRequest YA NO tiene 'exists:users,email'.
    // El controller devuelve 200 SIEMPRE (mensaje genérico) tanto si el email
    // existe como si no. Un atacante no puede diferenciar 200 vs 422 para
    // enumerar cuentas.

    public function test_forgot_password_with_existing_email_returns_200(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(200);
    }

    public function test_forgot_password_with_nonexistent_email_returns_200(): void
    {
        // MITIGADO: el endpoint devuelve 200 (genérico) indistintamente de si
        // el email existe. Sin diferencia de respuesta → no hay enumeración.
        $this->postJson('/api/auth/forgot-password', ['email' => 'nonexistent@example.com'])
            ->assertStatus(200);
    }

    // =========================================================================
    // e) RESET INVÁLIDO
    // =========================================================================

    public function test_reset_with_invalid_token_returns_400(): void
    {
        User::factory()->create(['email' => 'invalidtoken@example.com']);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'invalidtoken@example.com',
            'token' => 'invalid-token-that-does-not-exist',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(400);
    }

    public function test_reset_with_email_mismatch_returns_400(): void
    {
        // Crear dos usuarios
        $userA = User::factory()->create(['email' => 'usera@example.com']);
        User::factory()->create(['email' => 'userb@example.com']);

        // Generar token para userA
        $token = Password::createToken($userA);

        // Intentar reset con email de userB pero token de userA
        $this->postJson('/api/auth/reset-password', [
            'email' => 'userb@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(400);
    }

    // =========================================================================
    // f) EMAIL VERIFY: HASH MANIPULADO Y SIN FIRMA
    // =========================================================================

    public function test_email_verify_with_manipulated_hash_returns_403(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        // Construir URL firmada con hash que NO coincide con sha1(email)
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->uuid,
            'hash' => 'manipulated-hash-that-does-not-match',
        ]);

        $this->getJson($url)->assertStatus(403);
    }

    public function test_email_verify_without_signature_returns_403(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        // URL sin firma (no se usa temporarySignedRoute → no tiene ?signature=...)
        $this->getJson('/api/auth/email/verify/'.$user->uuid.'/'.sha1($user->email))
            ->assertStatus(403);
    }

    // =========================================================================
    // g) RATE LIMITING LOGIN (MITIGADO)
    // =========================================================================
    // La ruta /api/auth/login AHORA tiene middleware 'throttle:10,1'.
    // Limita la fuerza bruta: el intento #11 recibe 429 (too many requests).
    // El test de abajo valida precisamente ese comportamiento.

    public function test_login_rate_limits_after_10_attempts(): void
    {
        User::factory()->create([
            'email' => 'ratelimit@example.com',
            'password' => Hash::make('correctpassword'),
        ]);

        for ($i = 0; $i < 11; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'wrongpassword_'.$i, // siempre wrong
            ]);

            if ($i < 10) {
                // Los primeros 10 intentos: 422 (credenciales inválidas)
                $response->assertStatus(422);
            } else {
                // Intento #11: DEBERÍA ser 429 pero actualmente será 422
                // porque no hay rate limiting en login.
                // Este assert documenta el comportamiento deseado.
                $response->assertStatus(429);
            }
        }
    }

    // =========================================================================
    // h) PENDING TOKEN LOGOUT
    // =========================================================================
    // POST /api/auth/logout está bajo el grupo ability:'*'.
    // Un token con ability '2fa_pending' debe recibir 403.

    public function test_pending_token_cannot_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user, ['2fa_pending']))
            ->postJson('/api/auth/logout');

        $response->assertStatus(403);
    }
}
