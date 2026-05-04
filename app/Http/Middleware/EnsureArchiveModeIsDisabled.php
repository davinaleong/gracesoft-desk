<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureArchiveModeIsDisabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && ! $request->routeIs('settings.system.edit', 'settings.system.update', 'logout')) {
            $archiveMode = strtolower((string) SystemSetting::query()
                ->where('key', 'archive_mode')
                ->value('value'));

            if (in_array($archiveMode, ['1', 'true', 'yes', 'on'], true)) {
                return redirect()
                    ->back()
                    ->with('status', 'archive-mode-read-only');
            }
        }

        return $next($request);
    }
}
