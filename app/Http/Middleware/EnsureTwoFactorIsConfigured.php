<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsConfigured
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($this->isSetupRoute($request)) {
            return $next($request);
        }

        if (! $user->two_factor_confirmed_at) {
            return redirect()
                ->route('profile.edit')
                ->with('status', 'two-factor-required');
        }

        return $next($request);
    }

    private function isSetupRoute(Request $request): bool
    {
        return $request->routeIs('profile.edit', 'password.update', 'logout')
            || $request->is(
                'user/two-factor-authentication',
                'user/confirmed-two-factor-authentication',
                'user/two-factor-qr-code',
                'user/two-factor-recovery-codes'
            );
    }
}
