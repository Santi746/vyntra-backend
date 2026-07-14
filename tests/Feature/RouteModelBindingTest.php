<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Tests\Feature\Auth\AuthTestCase;

class RouteModelBindingTest extends AuthTestCase
{
    public function test_implicit_binding_rejects_malformed_uuid_with_404(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        // Binding implícito (Club $club / User $user): UUID malformado
        // debe dar 404, nunca 500 por cast inválido en PostgreSQL.
        $this->getJson('/api/clubs/not-a-valid-uuid')->assertStatus(404);
        $this->getJson('/api/users/not-a-valid-uuid')->assertStatus(404);
    }

    public function test_manual_uuid_param_rejects_malformed_uuid_with_404(): void
    {
        $user = $this->createUser();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);
        $this->actingAs($user);

        // Param manual `string $member` protegido con whereUuid en la ruta.
        $this->deleteJson('/api/clubs/'.$club->uuid.'/members/not-a-valid-uuid')
            ->assertStatus(404);
    }

    public function test_valid_but_nonexistent_uuid_returns_404(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->getJson('/api/users/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    public function test_valid_existing_uuid_returns_200(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->getJson('/api/users/'.$user->uuid)->assertStatus(200);
    }
}
