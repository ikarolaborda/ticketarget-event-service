<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AuthTokenVerifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthTokenVerifier::class, fn (): AuthTokenVerifier => new AuthTokenVerifier(
            secret: (string) config('auth_token.secret'),
            issuer: (string) config('auth_token.issuer'),
        ));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);
    }
}
