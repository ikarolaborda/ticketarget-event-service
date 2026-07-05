<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminAuthTest extends TestCase
{
    use MintsAdminJwt;
    use RefreshDatabase;

    private const string SECRET = 'test-auth-secret';

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException('Refusing to refresh a non-sqlite database.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth_token.secret' => self::SECRET,
            'auth_token.issuer' => 'ticketarget-users',
            'auth_token.accept_hs256' => true,
        ]);

        $this->bindAdminJwks();
    }

    public function test_it_rejects_requests_without_a_bearer_token(): void
    {
        $this->postJson('/venues', $this->venuePayload())->assertStatus(401);
    }

    public function test_it_rejects_a_garbage_bearer_token(): void
    {
        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer not-a-real-token'])
            ->assertStatus(401);
    }

    public function test_it_rejects_a_jwt_with_a_tampered_signature(): void
    {
        $token = $this->adminJwt(isAdmin: true);
        $forged = substr($token, 0, -4).'AAAA';

        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$forged])
            ->assertStatus(401);
    }

    public function test_it_rejects_a_valid_jwt_without_the_admin_flag(): void
    {
        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$this->adminJwt(isAdmin: false)])
            ->assertStatus(403);
    }

    public function test_it_accepts_a_valid_admin_jwt(): void
    {
        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$this->adminJwt(isAdmin: true)])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'JWT Venue');

        $this->assertDatabaseHas('venues', ['name' => 'JWT Venue']);
    }

    public function test_it_rejects_an_admin_jwt_from_the_wrong_issuer(): void
    {
        $token = $this->adminJwt(isAdmin: true, issuer: 'someone-else');

        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
    }

    public function test_it_rejects_an_expired_admin_jwt(): void
    {
        $token = $this->adminJwt(isAdmin: true, expiresAt: time() - 60);

        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
    }

    public function test_it_rejects_an_rs256_token_signed_by_an_unknown_kid(): void
    {
        $token = $this->adminJwt(isAdmin: true, kid: 'rotated-away');

        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
    }

    public function test_it_accepts_a_legacy_hs256_admin_token_while_the_flag_is_on(): void
    {
        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$this->legacyHs256Jwt(true, self::SECRET)])
            ->assertStatus(201);
    }

    public function test_it_rejects_a_legacy_hs256_admin_token_once_the_flag_is_off(): void
    {
        config(['auth_token.accept_hs256' => false]);

        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$this->legacyHs256Jwt(true, self::SECRET)])
            ->assertStatus(401);
    }

    public function test_venues_listing_is_public(): void
    {
        $this->getJson('/venues')->assertOk()->assertJsonStructure(['data']);
    }

    private function venuePayload(): array
    {
        return ['name' => 'JWT Venue', 'address' => 'Rua Um, 1', 'city' => 'Recife', 'capacity' => 100];
    }
}
