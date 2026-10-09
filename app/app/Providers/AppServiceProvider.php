<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Auth\Notifications\ResetPassword;
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
        Sanctum::usePersonalAccessTokenModel(
            PersonalAccessToken::class,
        );

        ResetPassword::createUrlUsing(
            static function (object $notifiable, string $token): string {
                $url = config('app.password_reset_url');

                if (! is_string($url) || trim($url) === '') {
                    throw new \RuntimeException(
                        'Set PASSWORD_RESET_URL to the frontend password-reset page URL.'
                    );
                }

                return rtrim($url, '/') . '?' . http_build_query([
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ]);
            },
        );
        //
    }
}
