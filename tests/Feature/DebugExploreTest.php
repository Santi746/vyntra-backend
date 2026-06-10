<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class DebugExploreTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_explore(): void
    {
        $user = User::factory()->create();
        Club::factory()->count(3)->create();

        Sanctum::actingAs($user, 'sanctum');

        $response = $this->getJson('/api/explore');

        echo "\nStatus: " . $response->getStatusCode();
        echo "\nContent: " . $response->getContent();

        $response->assertStatus(200);
    }
}