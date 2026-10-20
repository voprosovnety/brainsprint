<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ConsumerProxy
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*') && app()->environment('production')) {
            $secret = config('release.proxy_secret');
            abort_unless(is_string($secret) && strlen($secret) >= 32 && hash_equals($secret, (string) $request->header('X-Consumer-Proxy')), 403, 'Use the BrainSprint website to continue.');
        }

        return $next($request);
    }
}
