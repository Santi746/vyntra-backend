<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificationController;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_notifications_with_cursor_pagination(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->create(['user_uuid' => $user->uuid]);
        Sanctum::actingAs($user);

        $controller = new NotificationController();
        $response = $controller->index(request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
    }

    public function test_index_returns_only_user_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Notification::factory()->count(2)->create(['user_uuid' => $user->uuid]);
        Notification::factory()->count(3)->create(['user_uuid' => $otherUser->uuid]);
        Sanctum::actingAs($user);

        $controller = new NotificationController();
        $response = $controller->index(request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->expectException(\Illuminate\Auth\AuthenticationException::class);

        $controller = new NotificationController();
        $controller->index(request());
    }

    public function test_mark_as_read_updates_notification(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_uuid' => $user->uuid,
            'is_read' => false,
        ]);
        Sanctum::actingAs($user);

        $controller = new NotificationController();
        $response = $controller->markAsRead(request(), $notification);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);

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
        Sanctum::actingAs($user);

        $controller = new NotificationController();

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $controller->markAsRead(request(), $notification);
    }

    public function test_mark_as_read_requires_authentication(): void
    {
        $this->expectException(\Illuminate\Auth\AuthenticationException::class);

        $controller = new NotificationController();
        $controller->markAsRead(request(), new Notification());
    }

    public function test_mark_as_read_returns_404_for_nonexistent_notification(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $nonExistentNotification = new Notification(['uuid' => 'nonexistent-uuid', 'user_uuid' => $user->uuid]);

        $controller = new NotificationController();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $controller->markAsRead(request(), $nonExistentNotification);
    }
}