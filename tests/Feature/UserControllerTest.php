<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_authenticated_user_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $controller = new UserController();
        $request = request();
        $response = $controller->me($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
    }

    public function test_show_returns_user_data_by_uuid(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $controller = new UserController();
        $response = $controller->show($user);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
    }

    public function test_show_returns_404_for_nonexistent_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a user with non-existent UUID for route model binding
        $nonExistentUser = new User();
        $nonExistentUser->uuid = 'nonexistent-uuid';

        try {
            $controller = new UserController();
            $response = $controller->show($nonExistentUser);
            $this->assertEquals(404, $response->getStatusCode());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_update_profile_updates_user_data(): void
    {
        $user = User::factory()->create([
            'username' => 'oldusername',
        ]);
        Sanctum::actingAs($user);

        $controller = new UserController();

        // Create a mock UpdateUserRequest
        $request = $this->app->make(UpdateUserRequest::class);
        $request->merge(['username' => 'newusername']);

        $response = $controller->updateProfile($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);

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
        Sanctum::actingAs($user);

        $controller = new UserController();
        $response = $controller->sessions(request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
    }
}