<?php

namespace Tests\Feature\Auth;

use App\Models\User;

class CompleteProfileTest extends AuthTestCase
{
    public function test_oauth_user_can_complete_profile(): void
    {
        $user = User::factory()->create([
            'username' => null,
            'first_name' => null,
            'last_name' => null,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson('/api/user/complete-profile', [
                'username' => 'chosenname',
                'first_name' => 'First',
                'last_name' => 'Last',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.username', 'chosenname');

        $this->assertNotNull($user->fresh()->username);
    }

    public function test_duplicate_username_is_rejected(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create(['username' => null]);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson('/api/user/complete-profile', [
                'username' => 'taken',
                'first_name' => 'First',
                'last_name' => 'Last',
            ])
            ->assertStatus(422);
    }
}
