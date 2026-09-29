<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Routes that are intentionally outside the standard guard stack.
 *
 * @return array<int, string>
 */
function routeGuardExemptions(): array
{
    return [
        'profile.edit',              // must stay reachable while a password change / 2FA setup is pending
        'settings.github.callback',  // OAuth redirect arrives mid-flow
        'webhooks.github',           // HMAC-verified, no session
    ];
}

/**
 * @return array<int, RoutingRoute>
 */
function guardedApplicationRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(function (RoutingRoute $route): bool {
            $controller = $route->getControllerClass();

            if ($controller === null || ! str_starts_with($controller, 'App\\Http\\Controllers\\')) {
                return false;
            }

            if (str_starts_with($controller, 'App\\Http\\Controllers\\Auth\\') || str_starts_with($controller, 'App\\Http\\Controllers\\Api\\')) {
                return false;
            }

            return ! in_array($route->getName(), routeGuardExemptions(), true);
        })
        ->values()
        ->all();
}

test('every application route sits behind auth, password.changed and twofactor.configured', function () {
    $routes = guardedApplicationRoutes();

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $middleware = $route->gatherMiddleware();

        foreach (['auth', 'password.changed', 'twofactor.configured'] as $required) {
            expect(in_array($required, $middleware, true))
                ->toBeTrue(sprintf('Route [%s %s] is missing the [%s] middleware.', implode('|', $route->methods()), $route->uri(), $required));
        }
    }
});

test('every non-GET application route is blocked in archive mode', function () {
    foreach (guardedApplicationRoutes() as $route) {
        if (array_diff($route->methods(), ['GET', 'HEAD']) === []) {
            continue;
        }

        expect(in_array('archive.open', $route->gatherMiddleware(), true))
            ->toBeTrue(sprintf('Route [%s %s] is missing the [archive.open] middleware.', implode('|', $route->methods()), $route->uri()));
    }
});

test('application route parameters bind by uuid, never by numeric id', function () {
    $this->actingAs(User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]));

    $client = Client::factory()->create();

    $this->get('/clients/'.$client->id)->assertNotFound();
    $this->get('/clients/'.$client->uuid)->assertOk();
});
