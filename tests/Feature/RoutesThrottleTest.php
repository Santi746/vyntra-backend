<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoutesThrottleTest extends TestCase
{
    use RefreshDatabase;

    private array $writeRoutes = [
        ['POST', 'api/auth/logout'],
        ['PATCH', 'api/user'],
        ['POST', 'api/user/friend-requests'],
        ['PATCH', 'api/user/friend-requests/{request_uuid}'],
        ['PATCH', 'api/notifications/{notification}/read'],
        ['POST', 'api/clubs'],
        ['PATCH', 'api/clubs/{club}'],
        ['DELETE', 'api/clubs/{club}'],
        ['POST', 'api/clubs/{club}/members'],
        ['DELETE', 'api/clubs/{club}/members/{member}'],
        ['POST', 'api/clubs/{club}/roles'],
        ['PATCH', 'api/clubs/{club}/roles'],
        ['DELETE', 'api/clubs/{club}/roles/{role}'],
        ['POST', 'api/clubs/{club}/members/{user}/roles'],
        ['POST', 'api/clubs/{club}/categories'],
        ['PATCH', 'api/clubs/{club}/categories'],
        ['DELETE', 'api/clubs/{club}/categories/{category}'],
        ['POST', 'api/clubs/{club}/channels'],
        ['PATCH', 'api/clubs/{club}/channels/{channel}'],
        ['DELETE', 'api/clubs/{club}/channels/{channel}'],
        ['POST', 'api/channels/{channel}/messages'],
        ['POST', 'api/dm-conversations'],
        ['POST', 'api/dm-conversations/{dm_conversation}/messages'],
    ];

    private array $readRoutes = [
        ['GET', 'api/user'],
        ['GET', 'api/user/sessions'],
        ['GET', 'api/users/{user}'],
        ['GET', 'api/user/friends'],
        ['GET', 'api/user/friend-requests'],
        ['GET', 'api/notifications'],
        ['GET', 'api/user/clubs'],
        ['GET', 'api/clubs/{club}/preview'],
        ['GET', 'api/clubs/{club}'],
        ['GET', 'api/clubs/{club}/members'],
        ['GET', 'api/clubs/{club}/roles'],
        ['GET', 'api/clubs/{club}/categories'],
        ['GET', 'api/clubs/{club}/channels'],
        ['GET', 'api/channels/{channel}/messages'],
        ['GET', 'api/user/dm-conversations'],
        ['GET', 'api/dm-conversations/{dm_conversation}'],
        ['GET', 'api/dm-conversations/{dm_conversation}/messages'],
        ['GET', 'api/explore'],
        ['GET', 'api/search'],
    ];

    public function test_write_routes_have_throttle_middleware(): void
    {
        foreach ($this->writeRoutes as [$method, $uri]) {
            $routes = Route::getRoutes()->getRoutesByMethod()[$method] ?? [];

            $found = false;
            foreach ($routes as $route) {
                if ($route->uri() === $uri) {
                    $this->assertContains(
                        'throttle:10,1',
                        $route->middleware(),
                        "{$method} {$uri} debe tener middleware throttle:10,1"
                    );
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $this->fail("Ruta {$method} {$uri} no encontrada en el route list");
            }
        }
    }

    public function test_read_routes_do_not_have_throttle(): void
    {
        foreach ($this->readRoutes as [$method, $uri]) {
            $routes = Route::getRoutes()->getRoutesByMethod()[$method] ?? [];

            $found = false;
            foreach ($routes as $route) {
                if ($route->uri() === $uri) {
                    $this->assertNotContains(
                        'throttle:10,1',
                        $route->middleware(),
                        "{$method} {$uri} NO debe tener middleware throttle:10,1"
                    );
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $this->fail("Ruta {$method} {$uri} no encontrada en el route list");
            }
        }
    }

    public function test_write_routes_accept_valid_requests(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $clientUuid = '550e8400-e29b-41d4-a716-446655440000';
        $response = $this->withToken($token)
            ->postJson('/api/clubs', [
                'client_uuid' => $clientUuid,
                'name' => 'Test Club',
                'category_tag' => 'general',
            ]);

        $response->assertStatus(201);
    }
}
