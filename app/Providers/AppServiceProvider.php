<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min(10);

            return app()->isProduction()
                ? $rule->mixedCase()->numbers()->uncompromised()
                : $rule;
        });

        RateLimiter::for('auth', function (Request $request) {
            $email = (string) $request->input('email', '');

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by(strtolower($email).'|'.$request->ip()),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}

