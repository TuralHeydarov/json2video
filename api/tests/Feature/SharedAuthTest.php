<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SharedAuth;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SharedAuthTest extends TestCase
{
    use RefreshDatabase;

    private $key;
    private string $subject = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        config(['shared_auth.enabled' => true, 'shared_auth.issuer' => 'https://id.invalid.test/auth/v1']);
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $details = openssl_pkey_get_details($this->key);
        $encode = fn ($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        Http::fake(['https://id.invalid.test/auth/v1/.well-known/jwks.json' => Http::response(['keys' => [[
            'kid' => 'fixture', 'alg' => 'ES256', 'use' => 'sig', 'kty' => 'EC', 'crv' => 'P-256',
            'x' => $encode($details['ec']['x']), 'y' => $encode($details['ec']['y']),
        ]]])]);
    }

    private function token(array $extra = []): string
    {
        return JWT::encode(array_merge([
            'iss' => 'https://id.invalid.test/auth/v1', 'aud' => 'authenticated',
            'sub' => $this->subject, 'role' => 'authenticated', 'iat' => time(), 'exp' => time() + 300,
        ], $extra), $this->key, 'ES256', 'fixture');
    }

    public function test_same_email_does_not_link_or_grant_admin(): void
    {
        $user = User::factory()->create(['email' => 'same@invalid.test', 'is_admin' => false]);
        $this->post('/shared/login', ['access_token' => $this->token([
            'email' => $user->email, 'user_metadata' => ['role' => 'ADMIN'],
        ])])->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseCount('shared_auth_identities', 0);
    }

    public function test_two_account_proofs_link_and_preserve_business_user(): void
    {
        $user = User::factory()->create(['password' => 'fixture-password', 'is_admin' => false]);
        $token = $this->token();
        $this->actingAs($user)->post('/shared/link', [
            'access_token' => $token, 'password' => 'fixture-password',
        ])->assertOk();
        $this->post('/logout');
        $this->post('/shared/login', ['access_token' => $token])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertFalse((bool) $user->fresh()->is_admin);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_wrong_password_cannot_link_and_binding_cannot_be_reassigned(): void
    {
        $user = User::factory()->create(['password' => 'fixture-password']);
        $this->actingAs($user)->post('/shared/link', [
            'access_token' => $this->token(), 'password' => 'wrong',
        ])->assertForbidden();
        DB::table('shared_auth_identities')->insert([
            'user_id' => $user->id, 'issuer' => 'https://id.invalid.test/auth/v1', 'subject' => $this->subject,
        ]);
        $other = User::factory()->create(['password' => 'fixture-password']);
        $this->actingAs($other)->post('/shared/link', [
            'access_token' => $this->token(), 'password' => 'fixture-password',
        ])->assertStatus(409);
    }

    public function test_invalid_tokens_and_expired_local_session_are_denied(): void
    {
        foreach ([['iss' => 'https://evil.invalid'], ['aud' => 'other'], ['exp' => 1],
            ['role' => 'service_role'], ['is_anonymous' => true]] as $extra) {
            $this->post('/shared/login', ['access_token' => $this->token($extra)])->assertUnauthorized();
        }
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['shared_auth_expires_at' => 1])
            ->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
