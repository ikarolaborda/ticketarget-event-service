<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AuthTokenVerifier;
use App\Services\Jwks\HttpJwksProvider;
use App\Services\Jwks\JwksProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwksProvider::class, fn (): JwksProvider => new HttpJwksProvider(
            url: (string) config('auth_token.jwks_url'),
            cacheTtlSeconds: (int) config('auth_token.jwks_cache_ttl_seconds'),
        ));

        $this->app->singleton(AuthTokenVerifier::class, fn ($app): AuthTokenVerifier => new AuthTokenVerifier(
            keys: $app->make(JwksProvider::class),
            issuer: (string) config('auth_token.issuer'),
            legacySecret: (string) config('auth_token.secret'),
            acceptHs256: (bool) config('auth_token.accept_hs256'),
        ));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);
    }
}
