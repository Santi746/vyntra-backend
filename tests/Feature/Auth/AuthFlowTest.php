<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthFlowTest extends AuthTestCase
{
    public function test_register_returns_201_and_token(): void
    {
        $email = 'newuser@example.com';

        $response = $this->postJson('/api/auth/register', [
            'username' => 'newuser',
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['status', 'data' => ['user', 'token', 'token_type', 'profile_completed']]);

        $this->assertDatabaseHas('users', ['email' => $email]);
        $user = User::where('email', $email)->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_login_without_2fa_returns_token(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ])->assertStatus(200)
            ->assertJsonMissingPath('data.requires_2fa')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_with_invalid_credentials_returns_422(): void
    {
        User::factory()->create([
            'email' => 'bad@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'bad@example.com',
            'password' => 'wrongpass',
        ])->assertStatus(422);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
