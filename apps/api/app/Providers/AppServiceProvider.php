<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\FunnelRun;
use App\Models\FunnelVersion;
use App\Policies\ReadOnlyPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/admin/livewire/update', $handle)->middleware('web'));
        foreach ([FunnelRun::class, Event::class, FunnelVersion::class] as $model) {
            Gate::policy($model, ReadOnlyPolicy::class);
        }
        // Next proxies all browsers through one origin: do not key every visitor by its IP.
        RateLimiter::for('analytics', fn (Request $request) => Limit::perMinute(40)->by(hash('sha256', $request->session()->getId())));
        RateLimiter::for('consumer', fn (Request $request) => Limit::perMinute(60)->by(hash('sha256', $request->session()->getId())));
    }
}
