<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        //
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return rtrim(config('app.password_reset_url'), '/').'?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(
                config('auth.rate_limits.login')
            )->by(
                strtolower($request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perMinute(
                config('auth.rate_limits.register')
            )->by(
                $request->ip()
            );
        });

        RateLimiter::for('auth-forgot-password', function (Request $request) {
            return Limit::perMinute(
                config('auth.rate_limits.forgot_password')
            )->by(
                strtolower($request->input('email')).'|'.$request->ip()
            );
        });

    }
}
