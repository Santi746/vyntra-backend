<?php

namespace Tests\Feature;

use App\Http\Controllers\SearchController;
use App\Models\Club;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_clubs_and_users_by_query(): void
    {
        $owner = User::factory()->create(['username' => 'testuser']);
        Club::factory()->create(['name' => 'Test Club']);
        Sanctum::actingAs(User::factory()->create());

        $controller = new SearchController;
        $request = new Request;
        $request->merge(['q' => 'Test']);

        $response = $controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('clubs', $data['data']);
        $this->assertArrayHasKey('users', $data['data']);
    }

    public function test_index_returns_clubs_only_with_filter(): void
    {
        $owner = User::factory()->create();
        Club::factory()->create(['name' => 'Gaming Club']);
        User::factory()->create(['username' => 'testuser']);
        Sanctum::actingAs(User::factory()->create());

        $controller = new SearchController;
        $request = new Request;
        $request->merge(['q' => 'Test', 'filter' => 'clubs']);

        $response = $controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['data']['clubs']);
        $this->assertNull($data['data']['users']);
    }

    public function test_index_returns_users_only_with_filter(): void
    {
        $owner = User::factory()->create();
        Club::factory()->create(['name' => 'Gaming Club']);
        User::factory()->create(['username' => 'testuser', 'user_tag' => '1234']);
        Sanctum::actingAs(User::factory()->create());

        $controller = new SearchController;
        $request = new Request;
        $request->merge(['q' => 'test', 'filter' => 'users']);

        $response = $controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertNull($data['data']['clubs']);
        $this->assertNotNull($data['data']['users']);
    }

    public function test_index_requires_query_parameter(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $controller = new SearchController;
        $request = new Request;
        // No 'q' parameter

        try {
            $response = $controller->index($request);
            $this->assertEquals(422, $response->getStatusCode());
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_index_requires_authentication(): void
    {
        $this->expectException(AuthenticationException::class);

        $controller = new SearchController;
        $controller->index(new Request);
    }

    public function test_index_searches_by_user_tag(): void
    {
        User::factory()->create(['username' => 'john', 'user_tag' => 'unique999']);
        Sanctum::actingAs(User::factory()->create());

        $controller = new SearchController;
        $request = new Request;
        $request->merge(['q' => 'unique999']);

        $response = $controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNotEmpty($data['data']['users']['data']);
    }

    public function test_index_searches_by_club_description(): void
    {
        $owner = User::factory()->create();
        Club::factory()->create([
            'name' => 'Some Club',
            'description' => 'This is about gaming',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $controller = new SearchController;
        $request = new Request;
        $request->merge(['q' => 'gaming']);

        $response = $controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNotEmpty($data['data']['clubs']['data']);
    }

    public function test_index_returns_empty_results_when_no_match(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $controller = new SearchController;
        $request = new Request;
        $request->merge(['q' => 'nonexistentquery12345']);

        $response = $controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEmpty($data['data']['clubs']['data']);
        $this->assertEmpty($data['data']['users']['data']);
    }

    public function test_index_validates_filter_value(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $controller = new SearchController;
        $request = new Request;
        $request->merge(['q' => 'test', 'filter' => 'invalid']);

        try {
            $response = $controller->index($request);
            $this->assertEquals(422, $response->getStatusCode());
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }
    }
}
