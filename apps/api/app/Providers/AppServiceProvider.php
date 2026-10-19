<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Next proxies all browsers through one origin: do not key every visitor by its IP.
        RateLimiter::for('consumer', fn (Request $request) => Limit::perMinute(60)->by(hash('sha256', $request->session()->getId())));
    }
}
