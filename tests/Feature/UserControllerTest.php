<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_authenticated_user_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data']);
    }

    public function test_show_returns_user_data_by_uuid(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/users/'.$user->uuid);

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data']);
    }

    public function test_show_returns_404_for_nonexistent_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->getJson('/api/users/nonexistent-uuid')->assertStatus(404);
    }

    public function test_update_profile_updates_user_data(): void
    {
        $user = User::factory()->create([
            'username' => 'oldusername',
        ]);
        $this->actingAs($user);

        $this->patchJson('/api/user', [
            'username' => 'newusername',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'uuid' => $user->uuid,
            'username' => 'newusername',
        ]);
    }

    public function test_sessions_returns_user_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('token-1')->plainTextToken;
        $user->createToken('token-2')->plainTextToken;
        $this->actingAs($user);

        $response = $this->getJson('/api/user/sessions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data']);
    }
}
