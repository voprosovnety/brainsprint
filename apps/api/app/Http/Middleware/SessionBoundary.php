<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Runs before cookies/session middleware, including Livewire update requests. */
class SessionBoundary
{
    public function handle(Request $request, Closure $next)
    {
        // Livewire keeps its original routes after registering a custom update route.
        // Only local static JS/CSS may use that prefix; all component actions belong to /admin.
        if ($request->is('livewire*') && (! $request->isMethod('GET') || $request->is('livewire*/preview-file/*') || ! preg_match('/\.(js|css)(\.map)?$/D', $request->path()))) {
            abort(404);
        }
        if ($request->is('admin', 'admin/*')) {
            config([
                'session.cookie' => 'brainsprint_admin_session',
                'session.path' => '/admin',
                'session.domain' => null,
                'session.table' => 'admin_sessions',
                'session.lifetime' => 120,
                'session.same_site' => 'strict',
            ]);
        }

        $response = $next($request);
        if ($request->is('admin', 'admin/*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Frame-Options', 'DENY');
        }

        return $response;
    }
}
