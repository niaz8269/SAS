<?php

namespace App\Providers;

use App\Domain\Documents\ClamAvScanner;
use App\Domain\Documents\FakeScanner;
use App\Domain\Documents\Scanner;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Scanner::class, function () {
            if (config('sas.uploads.scanner') === 'fake') {
                abort_if($this->app->isProduction(), 500, 'The fake scanner must never run in production.');

                return new FakeScanner;
            }

            return new ClamAvScanner(config('sas.uploads.clamav_host'), config('sas.uploads.clamav_port'));
        });
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceHttps(); // SEC-01
        }

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // AUTH-03 / AUTH-05: dotted ability names ("content.publish") are role permissions. Anything else falls through
        // to ordinary policies. Unknown dotted names are denied rather than ignored.
        Gate::before(function (?User $user, string $ability) {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return $user?->hasPermission($ability) ? true : false;
        });

        // AUTH-02: rate-limit login, registration, reset, verification, enquiries and uploads.
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(30)->by($r->ip()),
        ]);
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(10)->by($r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => Limit::perMinute(3)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('verification', fn (Request $r) => Limit::perMinute(3)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('enquiries', fn (Request $r) => [Limit::perMinute(3)->by($r->ip()), Limit::perDay(20)->by($r->ip())]);
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
    }
}
