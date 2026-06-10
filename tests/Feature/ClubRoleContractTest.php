<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubRoleContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ClubRoleController::index
     * Verifica que permissions viene como integer en la respuesta.
     */
    public function test_index_returns_roles_with_permissions_as_integer(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);
        $role = ClubRole::factory()->create([
            'club_uuid' => $club->uuid,
            'permissions' => 255,
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson("/api/clubs/{$club->uuid}/roles");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['uuid', 'name', 'color', 'permissions', 'is_fixed', 'sort_order'],
            ],
            'meta' => ['next_cursor', 'per_page'],
        ]);

        $data = $response->json('data.0');
        $this->assertIsInt($data['permissions'], 'permissions debe ser integer');
        $this->assertEquals(255, $data['permissions']);
    }

    /**
     * ClubRoleController::store
     * Verifica que se crea rol y devuelve 201.
     */
    public function test_store_creates_role_and_returns_201(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);

        $this->actingAs($user, 'sanctum');

        $payload = [
            'client_uuid' => fake()->uuid(),
            'name' => 'Moderador',
            'color' => '#FF5733',
            'permissions' => 127,
        ];

        $response = $this->postJson("/api/clubs/{$club->uuid}/roles", $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'status',
            'data' => ['uuid', 'club_uuid', 'name', 'color', 'permissions', 'is_fixed', 'sort_order'],
        ]);
        $response->assertJsonPath('status', 'success');
        $this->assertIsInt($response->json('data.permissions'));
    }

    /**
     * ClubRoleController::store
     * Verifica idempotencia (mismo client_uuid devuelve 200).
     */
    public function test_store_is_idempotent_returns_200_when_exists(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);
        $existingRole = ClubRole::factory()->create([
            'club_uuid' => $club->uuid,
            'client_uuid' => $clientUuid = fake()->uuid(),
        ]);

        $this->actingAs($user, 'sanctum');

        $payload = [
            'client_uuid' => $clientUuid,
            'name' => 'Otro Nombre',
            'color' => '#000000',
        ];

        $response = $this->postJson("/api/clubs/{$club->uuid}/roles", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.uuid', $existingRole->uuid);
    }

    /**
     * ClubRoleController::update
     * Verifica actualización de rol y permissions integer.
     */
    public function test_update_modifies_role_and_returns_success(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);
        $role = ClubRole::factory()->create([
            'club_uuid' => $club->uuid,
            'permissions' => 10,
        ]);

        $this->actingAs($user, 'sanctum');

        $payload = [
            'uuid' => $role->uuid,
            'client_uuid' => fake()->uuid(),
            'name' => 'Admin Actualizado',
            'color' => '#00FF00',
            'permissions' => 255,
        ];

        $response = $this->patchJson("/api/clubs/{$club->uuid}/roles", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.name', 'Admin Actualizado');
        $this->assertIsInt($response->json('data.permissions'));
        $this->assertEquals(255, $response->json('data.permissions'));
    }

    /**
     * ClubRoleController::destroy
     * Verifica que destroy retorna 204 noContent.
     */
    public function test_destroy_returns_204_no_content(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);
        $role = ClubRole::factory()->create(['club_uuid' => $club->uuid]);

        $this->actingAs($user, 'sanctum');

        $response = $this->deleteJson("/api/clubs/{$club->uuid}/roles/{$role->uuid}");

        $response->assertStatus(204);
        $this->assertEquals('', $response->getContent());
    }

    /**
     * Verifica validación de color hexadecimal.
     */
    public function test_store_validates_color_format(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/clubs/{$club->uuid}/roles", [
            'client_uuid' => fake()->uuid(),
            'name' => 'Test',
            'color' => 'invalid-color',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['color']);
    }
}