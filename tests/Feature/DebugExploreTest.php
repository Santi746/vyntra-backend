<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugExploreTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_explore(): void
    {
        $user = User::factory()->create();
        Club::factory()->count(3)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/explore');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }
}
