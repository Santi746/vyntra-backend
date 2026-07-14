<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class EmailVerificationTest extends AuthTestCase
{
    public function test_verify_with_valid_signed_url_marks_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->uuid,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertStatus(200);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verify_expired_url_returns_403(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('verification.verify', now()->subMinutes(60), [
            'id' => $user->uuid,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertStatus(403);
    }

    public function test_notification_requires_auth_and_returns_200(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/email/verification-notification')
            ->assertStatus(200);
    }

    public function test_notification_when_already_verified_returns_400(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/email/verification-notification')
            ->assertStatus(400);
    }
}
