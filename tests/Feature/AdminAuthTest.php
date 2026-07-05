<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminAuthTest extends TestCase
{
    use CreatesIdentityTables;
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

        config(['auth_token.secret' => self::SECRET, 'auth_token.issuer' => 'ticketarget-users']);

        $this->createIdentityTables();
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

    public function test_it_rejects_a_jwt_with_a_tampered_signature_without_falling_back_to_sanctum(): void
    {
        $token = $this->jwt(isAdmin: true);
        $forged = substr($token, 0, -4).'AAAA';

        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$forged])
            ->assertStatus(401);
    }

    public function test_it_rejects_a_valid_jwt_without_the_admin_flag(): void
    {
        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$this->jwt(isAdmin: false)])
            ->assertStatus(403);
    }

    public function test_it_accepts_a_valid_admin_jwt(): void
    {
        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$this->jwt(isAdmin: true)])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'JWT Venue');

        $this->assertDatabaseHas('venues', ['name' => 'JWT Venue']);
    }

    public function test_it_rejects_an_admin_jwt_from_the_wrong_issuer(): void
    {
        $token = $this->jwt(isAdmin: true, issuer: 'someone-else');

        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
    }

    public function test_it_rejects_an_expired_admin_jwt(): void
    {
        $token = $this->jwt(isAdmin: true, expiresAt: time() - 60);

        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
    }

    public function test_it_still_accepts_a_sanctum_token_with_the_events_write_ability(): void
    {
        $token = $this->sanctumUser()->createToken('cli', ['events:write'])->plainTextToken;

        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$token])
            ->assertStatus(201);
    }

    public function test_it_rejects_a_sanctum_token_without_the_events_write_ability(): void
    {
        $token = $this->sanctumUser()->createToken('cli', ['other:thing'])->plainTextToken;

        $this->postJson('/venues', $this->venuePayload(), ['Authorization' => 'Bearer '.$token])
            ->assertStatus(403);
    }

    public function test_venues_listing_is_public(): void
    {
        $this->getJson('/venues')->assertOk()->assertJsonStructure(['data']);
    }

    private function venuePayload(): array
    {
        return ['name' => 'JWT Venue', 'address' => 'Rua Um, 1', 'city' => 'Recife', 'capacity' => 100];
    }

    private function sanctumUser(): User
    {
        $user = new User;
        $user->name = 'CLI Admin';
        $user->email = 'cli@example.com';
        $user->password = 'irrelevant';
        $user->save();

        return $user;
    }

    private function jwt(bool $isAdmin, ?string $issuer = null, ?int $expiresAt = null): string
    {
        $encode = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

        $header = $encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $encode(json_encode([
            'iss' => $issuer ?? 'ticketarget-users',
            'sub' => '11111111-2222-3333-4444-555555555555',
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'is_admin' => $isAdmin,
            'iat' => time(),
            'exp' => $expiresAt ?? time() + 3600,
        ]));

        return $header.'.'.$payload.'.'.$encode(hash_hmac('sha256', $header.'.'.$payload, self::SECRET, true));
    }
}
