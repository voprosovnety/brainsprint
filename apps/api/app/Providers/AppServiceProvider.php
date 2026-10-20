<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\FunnelRun;
use App\Models\FunnelVersion;
use App\Policies\ReadOnlyPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (app()->environment('production')) {
            TrustProxies::at(config('release.trusted_proxies'));
            TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_PROTO);
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
            config(['app.debug' => false, 'session.secure' => true, 'session.domain' => null]);
        }
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/admin/livewire/update', $handle)->middleware('web'));
        foreach ([FunnelRun::class, Event::class, FunnelVersion::class] as $model) {
            Gate::policy($model, ReadOnlyPolicy::class);
        }
        // Next proxies all browsers through one origin: do not key every visitor by its IP.
        RateLimiter::for('analytics', fn (Request $request) => [Limit::perMinute(max(1, config('release.analytics_per_minute')))->by('analytics:'.hash('sha256', $request->session()->getId())), Limit::perMinute(max(1, config('release.global_per_minute')))->by('global-analytics')]);
        RateLimiter::for('consumer', fn (Request $request) => [Limit::perMinute(max(1, config('release.consumer_per_minute')))->by('consumer:'.hash('sha256', $request->session()->getId())), Limit::perMinute(max(1, config('release.global_per_minute')))->by('global-consumer')]);
    }
}
