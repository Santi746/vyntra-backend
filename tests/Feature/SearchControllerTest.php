<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_clubs_and_users_by_query(): void
    {
        User::factory()->create(['username' => 'testuser']);
        Club::factory()->create(['name' => 'Test Club']);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/search?q=Test');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data' => ['clubs', 'users']]);
    }

    public function test_index_returns_clubs_only_with_filter(): void
    {
        Club::factory()->create(['name' => 'Gaming Club']);
        User::factory()->create(['username' => 'testuser']);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/search?q=Test&filter=clubs');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotNull($data['clubs']);
        $this->assertNull($data['users']);
    }

    public function test_index_returns_users_only_with_filter(): void
    {
        Club::factory()->create(['name' => 'Gaming Club']);
        User::factory()->create(['username' => 'testuser', 'user_tag' => '1234']);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/search?q=test&filter=users');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNull($data['clubs']);
        $this->assertNotNull($data['users']);
    }

    public function test_index_requires_query_parameter(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/search')->assertStatus(422);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/search?q=test')->assertStatus(401);
    }

    public function test_index_searches_by_user_tag(): void
    {
        User::factory()->create(['username' => 'john', 'user_tag' => 'unique999']);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/search?q=unique999');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.users.data'));
    }

    public function test_index_searches_by_club_description(): void
    {
        Club::factory()->create([
            'name' => 'Some Club',
            'description' => 'This is about gaming',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/search?q=gaming');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.clubs.data'));
    }

    public function test_index_returns_empty_results_when_no_match(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/search?q=nonexistentquery12345');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.clubs.data'));
        $this->assertEmpty($response->json('data.users.data'));
    }

    public function test_index_validates_filter_value(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/search?q=test&filter=invalid')->assertStatus(422);
    }
}
