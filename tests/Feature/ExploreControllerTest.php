<?php

namespace Tests\Feature;

use App\Http\Controllers\ExploreController;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExploreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_public_clubs_with_cursor_pagination(): void
    {
        $owner = User::factory()->create();
        Club::factory()->count(3)->create(['owner_uuid' => $owner->uuid]);
        Sanctum::actingAs(User::factory()->create());

        $controller = new ExploreController();
        $response = $controller->index(request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
    }

    public function test_index_returns_empty_list_when_no_clubs(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $controller = new ExploreController();
        $response = $controller->index(request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals([], $data['data']);
    }

    public function test_index_includes_club_owner_relationship(): void
    {
        $owner = User::factory()->create();
        Club::factory()->create(['owner_uuid' => $owner->uuid]);
        Sanctum::actingAs(User::factory()->create());

        $controller = new ExploreController();
        $response = $controller->index(request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $clubData = $data['data'][0];
        $this->assertArrayHasKey('owner', $clubData);
    }

    public function test_index_works_without_authentication(): void
    {
        $controller = new ExploreController();
        $response = $controller->index(request());

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }
}