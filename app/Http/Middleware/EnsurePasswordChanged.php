<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
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

        if ($user->must_change_password && ! $request->routeIs('profile.edit', 'profile.update', 'logout', 'password.update')) {
            return redirect()
                ->route('profile.edit')
                ->with('status', 'password-change-required');
        }

        return $next($request);
    }
}
