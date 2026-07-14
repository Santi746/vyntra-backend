<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class SocialiteTest extends AuthTestCase
{
    private function mockSocialUser(string $id, string $email, ?string $avatar = null): SocialiteUser
    {
        $socialUser = new SocialiteUser;
        $socialUser->id = $id;
        $socialUser->email = $email;
        $socialUser->avatar = $avatar ?? 'http://example.com/avatar.png';

        Socialite::shouldReceive('driver')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialUser);

        return $socialUser;
    }

    public function test_callback_creates_new_user_and_redirects(): void
    {
        $this->mockSocialUser('gh-123', 'oauth@example.com');

        $response = $this->getJson('/api/auth/social/github/callback');
        $response->assertStatus(302);
        $this->assertStringContainsString(config('app.frontend_url'), $response->headers->get('Location'));

        $this->assertDatabaseHas('users', [
            'email' => 'oauth@example.com',
            'provider' => 'github',
            'provider_id' => 'gh-123',
        ]);
    }

    public function test_callback_links_existing_email_account(): void
    {
        User::factory()->create([
            'email' => 'oauth2@example.com',
            'provider' => null,
            'provider_id' => null,
        ]);

        $this->mockSocialUser('gh-456', 'oauth2@example.com');

        $response = $this->getJson('/api/auth/social/github/callback');
        $response->assertStatus(302);
        $this->assertStringContainsString(config('app.frontend_url'), $response->headers->get('Location'));

        $this->assertDatabaseHas('users', [
            'email' => 'oauth2@example.com',
            'provider' => 'github',
            'provider_id' => 'gh-456',
        ]);
    }
}
