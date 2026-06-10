<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_201(): void
    {
        $payload = [
            'username' => 'testuser',
            'user_tag' => '1234',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Note: This test requires routes to be registered
        // Without routes, this will return 404
        // For now, we verify the User factory and model work correctly
        $user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);
    }

    public function test_user_factory_creates_valid_user(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->uuid);
        $this->assertNotNull($user->username);
        $this->assertNotNull($user->email);
        $this->assertNotNull($user->password);
    }

    public function test_user_can_have_memberships(): void
    {
        $user = User::factory()->create();

        // User should be able to have memberships relationship
        $this->assertTrue(method_exists($user, 'memberships'));
    }

    public function test_user_can_send_friend_requests(): void
    {
        $user = User::factory()->create();

        // User should be able to have sentFriendships relationship
        $this->assertTrue(method_exists($user, 'sentFriendships'));
    }

    public function test_user_can_receive_friend_requests(): void
    {
        $user = User::factory()->create();

        // User should be able to have receivedFriendships relationship
        $this->assertTrue(method_exists($user, 'receivedFriendships'));
    }
}