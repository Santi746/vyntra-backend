<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutesWorkTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_routes(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'username' => 'testuser',
            'user_tag' => 'test-001',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $register->assertStatus(201);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'test@test.com',
            'password' => 'password',
        ]);
        $login->assertStatus(200);
    }

    public function test_protected_routes_return_401_without_token(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
        $this->getJson('/api/user/clubs')->assertStatus(401);
        $this->postJson('/api/clubs', [])->assertStatus(401);
        $this->getJson('/api/explore')->assertStatus(401);
        $this->getJson('/api/search?q=test')->assertStatus(401);
    }
}
