<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Jwks\JwksProvider;
use Illuminate\Support\Str;

/**
 * Mints RS256 platform JWTs for the admin guard and binds an in-memory JWKS
 * so tests never touch the network or a private key on disk. One ephemeral
 * keypair per process. Verification is DB-free, so no identity tables needed.
 */
trait MintsAdminJwt
{
    private const string ADMIN_KID = 'test-kid';

    private static ?string $adminPrivateKeyPem = null;

    protected function bindAdminJwks(): void
    {
        $publicPem = $this->adminPublicKeyPem();

        $this->app->instance(JwksProvider::class, new class($publicPem, self::ADMIN_KID) implements JwksProvider
        {
            public function __construct(private string $publicPem, private string $kid) {}

            public function publicKeyPem(string $kid): ?string
            {
                return $kid === $this->kid ? $this->publicPem : null;
            }
        });
    }

    protected function adminJwt(
        bool $isAdmin = true,
        ?string $issuer = null,
        ?int $expiresAt = null,
        ?string $kid = null,
    ): string {
        $header = $this->b64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid ?? self::ADMIN_KID], JSON_THROW_ON_ERROR));
        $payload = $this->b64Url(json_encode([
            'iss' => $issuer ?? (string) config('auth_token.issuer'),
            'sub' => (string) Str::uuid(),
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'is_admin' => $isAdmin,
            'iat' => time(),
            'exp' => $expiresAt ?? time() + 3600,
        ], JSON_THROW_ON_ERROR));

        $signature = '';
        openssl_sign($header.'.'.$payload, $signature, self::adminPrivateKeyPem(), OPENSSL_ALGO_SHA256);

        return $header.'.'.$payload.'.'.$this->b64Url($signature);
    }

    protected function legacyHs256Jwt(bool $isAdmin, string $secret): string
    {
        $header = $this->b64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->b64Url(json_encode([
            'iss' => (string) config('auth_token.issuer'),
            'sub' => (string) Str::uuid(),
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'is_admin' => $isAdmin,
            'iat' => time(),
            'exp' => time() + 3600,
        ], JSON_THROW_ON_ERROR));
        $signature = $this->b64Url(hash_hmac('sha256', $header.'.'.$payload, $secret, true));

        return $header.'.'.$payload.'.'.$signature;
    }

    private function adminPublicKeyPem(): string
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private(self::adminPrivateKeyPem()));

        return (string) $details['key'];
    }

    private static function adminPrivateKeyPem(): string
    {
        if (self::$adminPrivateKeyPem === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $pem = '';
            openssl_pkey_export($key, $pem);
            self::$adminPrivateKeyPem = $pem;
        }

        return self::$adminPrivateKeyPem;
    }

    private function b64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
