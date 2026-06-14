<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_notifications_with_cursor_pagination(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->create(['user_uuid' => $user->uuid]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta' => ['next_cursor', 'per_page']]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_returns_only_user_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Notification::factory()->count(2)->create(['user_uuid' => $user->uuid]);
        Notification::factory()->count(3)->create(['user_uuid' => $otherUser->uuid]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(401);
    }

    public function test_mark_as_read_updates_notification(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_uuid' => $user->uuid,
            'is_read' => false,
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->patchJson("/api/notifications/{$notification->uuid}/read");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('notifications', [
            'uuid' => $notification->uuid,
            'is_read' => true,
        ]);
    }

    public function test_mark_as_read_returns_403_for_other_users_notification(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_uuid' => $otherUser->uuid,
            'is_read' => false,
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->patchJson("/api/notifications/{$notification->uuid}/read");

        $response->assertStatus(403);
    }

    public function test_mark_as_read_requires_authentication(): void
    {
        $notification = Notification::factory()->create();

        $response = $this->patchJson("/api/notifications/{$notification->uuid}/read");

        $response->assertStatus(401);
    }

    public function test_mark_as_read_returns_404_for_nonexistent_notification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->patchJson('/api/notifications/nonexistent-uuid/read');

        $response->assertStatus(404);
    }
}
