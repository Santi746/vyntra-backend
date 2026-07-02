<?php

namespace Tests\Feature;

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

        $response = $this->getJson('/api/explore');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_returns_empty_list_when_no_clubs(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/explore');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    public function test_index_includes_club_owner_relationship(): void
    {
        $owner = User::factory()->create();
        Club::factory()->create(['owner_uuid' => $owner->uuid]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/explore');

        $response->assertStatus(200);
        $clubData = $response->json('data')[0];
        $this->assertArrayHasKey('owner', $clubData);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/explore')->assertStatus(401);
    }
}
