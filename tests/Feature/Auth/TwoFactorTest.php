<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TwoFactorTest extends AuthTestCase
{
    private function enable(User $user, string $password): string
    {
        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/2fa/enable', ['password' => $password]);

        $response->assertStatus(200);

        return $response->json('data.secret');
    }

    public function test_enable_confirm_verify_login_pending_disable_flow(): void
    {
        $password = 'password123';
        $user = User::factory()->create(['password' => Hash::make($password)]);

        // 1) Enable
        $secret = $this->enable($user, $password);
        $this->assertNotEmpty($secret);

        // 2) Confirm with valid TOTP
        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/2fa/confirm', ['code' => $this->totp($secret)])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['backup_codes']]);

        $user->refresh();
        $this->assertTrue($user->has2faEnabled());

        // 3) Login now returns a pending token
        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertStatus(200)
            ->assertJsonPath('data.requires_2fa', true);

        $pending = $login->json('data.token');
        $this->assertNotEmpty($pending);

        // 4) Verify with TOTP using the pending token -> full token
        $this->withHeader('Authorization', 'Bearer '.$pending)
            ->postJson('/api/auth/2fa/verify', ['code' => $this->totp($secret)])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['token']]);

        // 5) Disable (password + TOTP)
        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/2fa/disable', [
                'password' => $password,
                'code' => $this->totp($secret),
            ])
            ->assertStatus(200);

        $user->refresh();
        $this->assertFalse($user->has2faEnabled());
    }

    public function test_enable_rejects_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/2fa/enable', ['password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_verify_rejects_bad_code(): void
    {
        $password = 'password123';
        $user = User::factory()->create(['password' => Hash::make($password)]);
        $secret = $this->enable($user, $password);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/2fa/confirm', ['code' => $this->totp($secret)])
            ->assertStatus(200);

        $pending = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$pending)
            ->postJson('/api/auth/2fa/verify', ['code' => '000000'])
            ->assertStatus(422);
    }
}
