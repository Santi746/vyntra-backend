<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordResetTest extends AuthTestCase
{
    public function test_forgot_password_always_returns_200(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(200);
    }

    public function test_reset_with_valid_token_returns_200_and_updates_password(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(200)
            ->assertJsonStructure(['status', 'data' => ['user', 'token']]);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_reset_with_invalid_token_returns_400(): void
    {
        User::factory()->create(['email' => 'reset2@example.com']);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset2@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(400);
    }

    public function test_password_reset_route_redirects_to_frontend(): void
    {
        $user = User::factory()->create(['email' => 'reset3@example.com']);
        $token = Password::createToken($user);

        $response = $this->get('/api/reset-password/'.$token.'?email='.urlencode($user->email));

        $response->assertStatus(302);
        $this->assertStringContainsString(
            config('app.frontend_url'),
            $response->headers->get('Location')
        );
    }
}
