<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExploreContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ExploreController::index
     * Verifica que la respuesta paginada NO tiene campo 'status'.
     */
    public function test_index_returns_clubs_without_status_field(): void
    {
        $user = User::factory()->create();
        Club::factory()->count(3)->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/explore');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'uuid',
                    'name',
                    'description',
                    'category_tag',
                    'avatar_url',
                    'banner_url',
                    'is_verified',
                    'created_at',
                    'updated_at',
                    'owner_uuid',
                ],
            ],
            'meta' => ['next_cursor', 'per_page'],
        ]);

        // Verificar que NO existe 'status' en la respuesta
        $json = $response->json();
        $this->assertFalse(isset($json['status']), 'Respuesta no debe tener key status');
        $data0 = $response->json('data.0');
        $this->assertFalse(isset($data0['status']), 'Item data no debe tener key status');
    }

    /**
     * Verifica que devuelve clubes ordenados por created_at desc.
     */
    public function test_index_returns_clubs_ordered_by_created_at_desc(): void
    {
        $user = User::factory()->create();
        $club1 = Club::factory()->create(['created_at' => now()->subDays(2)]);
        $club2 = Club::factory()->create(['created_at' => now()->subDay()]);
        $club3 = Club::factory()->create(['created_at' => now()]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/explore');

        $response->assertStatus(200);
        $uuids = collect($response->json('data'))->pluck('uuid')->toArray();

        // El club más reciente debe estar primero
        $this->assertEquals($club3->uuid, $uuids[0]);
    }

    /**
     * Verifica que incluye owner con clubOwner.
     */
    public function test_index_includes_owner_relationship(): void
    {
        $user = User::factory()->create();
        Club::factory()->create(['owner_uuid' => $user->uuid]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/explore');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['owner'],
            ],
        ]);
        $this->assertTrue(isset($response->json('data.0')['owner']));
    }

    /**
     * Verifica paginación con cursor.
     */
    public function test_index_pagination_works(): void
    {
        $user = User::factory()->create();
        Club::factory()->count(20)->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/explore?cursor=');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['next_cursor', 'per_page'],
        ]);
        $this->assertCount(15, $response->json('data')); // per_page = 15
    }
}
