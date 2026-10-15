<?php

namespace App\Providers;

use App\Models\User;
use App\Services\CompanyProfileResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CompanyProfileResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::define('viewApiDocs', fn (?User $user): bool => (bool) $user?->isAdmin());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('api-auth', fn (Request $request) => Limit::perMinute(6)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        ));
    }
}
