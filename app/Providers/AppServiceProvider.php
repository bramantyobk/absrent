<?php

namespace App\Providers;

use App\Models\User;
use App\Services\CompanyProfileResolver;
use App\Services\RentalSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
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
        $this->app->singleton(RentalSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Contoh: "Senin, 27 Oktober 2026"
        Carbon::macro('toDateIndonesian', function (): string {
            /** @var Carbon $this */
            return $this->copy()->locale('id')->translatedFormat('l, j F Y');
        });

        // Contoh: "Senin, 27 Oktober 2026 pukul 09:00 WIB"
        Carbon::macro('toLongIndonesian', function (bool $withZone = true): string {
            /** @var Carbon $this */
            $text = $this->toDateIndonesian().' pukul '.$this->format('H:i');

            return $withZone ? $text.' '.config('rental.timezone_label') : $text;
        });

        $this->app->make(RentalSettings::class)->apply();

        Gate::define('viewApiDocs', fn (?User $user): bool => (bool) $user?->isAdmin());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('api-auth', fn (Request $request) => Limit::perMinute(6)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        ));
    }
}
