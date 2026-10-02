<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plan;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SharedAuthTest extends TestCase
{
    use RefreshDatabase;

    private $key;
    private array $codes = [];
    private int $refreshes = 0;
    private int $logoutStatus = 204;
    private string $issuer = 'https://id.invalid.test/auth/v1';
    private string $client = '00000000-0000-4000-8000-000000000099';

    protected function setUp(): void
    {
        if (!preg_match('/rehearsal|test_/', getenv('DB_DATABASE') ?: '')) {
            throw new \RuntimeException('Native Auth tests require an isolated PostgreSQL fixture database');
        }
        parent::setUp();
        config(['shared_auth.enabled' => true, 'shared_auth.issuer' => $this->issuer,
            'shared_auth.client_id' => $this->client, 'shared_auth.client_secret' => 'fixture-only',
            'shared_auth.callback' => 'https://json2video.invalid.test/shared/callback',
            'app.url' => 'https://json2video.invalid.test', 'session.driver' => 'array',
            'session.cookie' => '__Host-json2video-session', 'session.secure' => true,
            'session.domain' => null, 'session.path' => '/', 'session.http_only' => true, 'session.same_site' => 'lax']);
        DB::statement('CREATE SCHEMA IF NOT EXISTS tural_auth');
        DB::statement('CREATE TABLE IF NOT EXISTS tural_auth.fixture_sessions (session_id uuid PRIMARY KEY, subject uuid NOT NULL, active boolean NOT NULL)');
        DB::unprepared('CREATE OR REPLACE FUNCTION tural_auth.session_active(uuid,uuid) RETURNS boolean LANGUAGE sql AS $$ SELECT EXISTS(SELECT 1 FROM tural_auth.fixture_sessions s WHERE s.session_id=$1 AND s.subject=$2 AND s.active) $$');
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $details = openssl_pkey_get_details($this->key);
        $encode = fn ($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($details, $encode) {
            if ($request->url() === $this->issuer.'/.well-known/jwks.json') {
                return Http::response(['keys' => [['kid' => 'fixture', 'alg' => 'ES256', 'use' => 'sig',
                    'kty' => 'EC', 'crv' => 'P-256', 'x' => $encode($details['ec']['x']), 'y' => $encode($details['ec']['y'])]]]);
            }
            if ($request->url() === $this->issuer.'/oauth/token') {
                $this->assertSame('Basic '.base64_encode($this->client.':fixture-only'), $request->header('Authorization')[0]);
                $entry = $request['grant_type'] === 'refresh_token'
                    ? collect($this->codes)->first(fn ($v) => $v['refresh'] === $request['refresh_token'])
                    : ($this->codes[$request['code']] ?? null);
                $this->assertNotNull($entry);
                if ($request['grant_type'] === 'authorization_code') {
                    $this->assertSame($entry['challenge'], $encode(hash('sha256', $request['code_verifier'], true)));
                    $this->assertSame(config('shared_auth.callback'), $request['redirect_uri']);
                } else { $this->refreshes++; }
                $base = ['iss' => $this->issuer, 'sub' => $entry['subject'], 'iat' => time(), 'exp' => time() + 300];
                $access = array_merge($base, ['aud' => 'authenticated', 'role' => 'authenticated',
                    'session_id' => $entry['session'], 'client_id' => $this->client,
                    'exp' => time() + ($request['grant_type'] === 'refresh_token' ? 300 : ($entry['ttl'] ?? 300))], $entry['bad_access'] ?? []);
                $identity = array_merge($base, ['aud' => $this->client, 'nonce' => $entry['nonce'],
                    'email' => $entry['email'], 'email_verified' => true, 'user_metadata' => ['is_admin' => true]], $entry['bad_id'] ?? []);
                return Http::response(['access_token' => JWT::encode($access, $this->key, 'ES256', 'fixture'),
                    'refresh_token' => $entry['refresh'], 'id_token' => JWT::encode($identity, $this->key, 'ES256', 'fixture')]);
            }
            if ($request->url() === $this->issuer.'/logout?scope=global') {
                if ($this->logoutStatus === 204) DB::table('tural_auth.fixture_sessions')->update(['active' => false]);
                return Http::response(null, $this->logoutStatus);
            }
            throw new \RuntimeException('Unexpected network in offline fixture');
        });
    }

    private function flow(string $intent = 'signup', array $extra = []): array
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.(count($this->codes) + 1)]);
        $response = $intent === 'link'
            ? $this->post('/shared/link/start', ['password' => 'fixture-password'])
            : $this->get('/shared/start?intent='.$intent);
        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $entry = array_merge(['subject' => (string) Str::uuid(), 'session' => (string) Str::uuid(),
            'email' => 'new-'.Str::random(8).'@invalid.test', 'refresh' => 'fixture-refresh-'.Str::uuid(),
            'nonce' => $query['nonce'], 'challenge' => $query['code_challenge']], $extra);
        DB::table('tural_auth.fixture_sessions')->insert(['session_id' => $entry['session'], 'subject' => $entry['subject'], 'active' => true]);
        $code = (string) Str::uuid(); $this->codes[$code] = $entry;
        return ['state' => $query['state'], 'code' => $code, 'entry' => $entry];
    }

    private function finishFlow(array $flow)
    {
        return $this->get('/shared/callback?'.http_build_query(['state' => $flow['state'], 'code' => $flow['code']]));
    }

    public function test_link_preserves_ids_plan_admin_and_refresh_is_server_only(): void
    {
        $user = User::factory()->create(['password' => 'fixture-password', 'is_admin' => true]);
        $this->actingAs($user);
        $flow = $this->flow('link', ['email' => $user->email, 'ttl' => 20]);
        $response = $this->finishFlow($flow)->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue((bool) $user->fresh()->is_admin);
        $this->assertSame($user->plan_id, $user->fresh()->plan_id);
        $this->assertStringNotContainsString($flow['entry']['refresh'], DB::table('shared_browser_sessions')->value('payload'));
        $this->assertStringNotContainsString($flow['entry']['refresh'], (string) $response->getContent());
        $this->get('/shared/account')->assertOk();
        $this->assertSame(1, $this->refreshes);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'fixture-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_same_email_is_not_a_binding_and_idp_metadata_cannot_grant_admin(): void
    {
        $user = User::factory()->create(['email' => 'existing@invalid.test', 'is_admin' => false]);
        $flow = $this->flow('signup', ['email' => strtoupper($user->email)]);
        $this->finishFlow($flow)->assertRedirect('/login');
        $this->assertGuest(); $this->assertDatabaseCount('shared_auth_identities', 0);
        $free = Plan::create(['name' => 'Fixture Free', 'slug' => 'free']);
        $this->finishFlow($this->flow())->assertRedirect('/dashboard');
        $this->assertFalse((bool) Auth::user()->is_admin);
        $this->assertSame($free->id, Auth::user()->plan_id);
        $this->assertDatabaseCount('users', 2); $this->assertDatabaseCount('api_keys', 1);
    }

    public function test_callback_state_is_one_use_and_pkce_is_bound(): void
    {
        $flow = $this->flow();
        $pending = session('shared_pending');
        $this->finishFlow($flow)->assertRedirect('/dashboard');
        $this->withSession(['shared_pending' => $pending]);
        $this->finishFlow($flow)->assertRedirect('/login');
        $this->assertDatabaseCount('users', 1);
        $this->post('/logout');
        $bad = $this->flow(); $bad['state'] = str_repeat('a', 43);
        $this->finishFlow($bad)->assertRedirect('/login');
        $this->assertGuest(); $this->assertDatabaseCount('users', 1);
    }

    public function test_nonce_issuer_audience_client_expiry_anonymous_are_rejected(): void
    {
        foreach ([['bad_id' => ['nonce' => 'wrong']], ['bad_id' => ['aud' => 'other']],
            ['bad_access' => ['iss' => 'https://evil.invalid']], ['bad_access' => ['exp' => 1]],
            ['bad_access' => ['role' => 'service_role']], ['bad_access' => ['is_anonymous' => true]],
            ['bad_access' => ['client_id' => (string) Str::uuid()]]] as $extra) {
            $this->finishFlow($this->flow('signup', $extra))->assertRedirect('/login');
            $this->assertGuest();
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_two_proofs_required_and_bindings_cannot_be_reassigned(): void
    {
        $this->post('/shared/link/start', ['password' => 'fixture-password'])->assertRedirect('/login');
        $user = User::factory()->create(['password' => 'fixture-password']);
        $this->actingAs($user)->post('/shared/link/start', ['password' => 'wrong'])->assertForbidden();
        $first = $this->flow('link'); $this->finishFlow($first)->assertRedirect('/dashboard');
        $this->post('/logout');
        $other = User::factory()->create(['password' => 'fixture-password']);
        $this->actingAs($other);
        $second = $this->flow('link', ['subject' => $first['entry']['subject']]);
        $this->finishFlow($second)->assertRedirect('/login');
        $this->assertDatabaseMissing('shared_auth_identities', ['user_id' => $other->id]);
    }

    public function test_native_global_revocation_ends_other_browser_and_cookie_scope_is_private(): void
    {
        $flow = $this->flow();
        $response = $this->finishFlow($flow)->assertRedirect('/dashboard');
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === '__Host-json2video-session');
        $this->assertNotNull($cookie); $this->assertTrue($cookie->isSecure()); $this->assertTrue($cookie->isHttpOnly());
        $this->assertNull($cookie->getDomain()); $this->assertSame('lax', $cookie->getSameSite()); $this->assertSame('/', $cookie->getPath());
        DB::table('tural_auth.fixture_sessions')->where('session_id', $flow['entry']['session'])->update(['active' => false]);
        $this->get('/shared/account')->assertRedirect('/login'); $this->assertGuest();
        $this->finishFlow($this->flow())->assertRedirect('/dashboard');
        $this->post('/shared/logout')->assertRedirect('/login'); $this->assertGuest();
        $this->assertDatabaseCount('shared_browser_sessions', 0);
    }
    public function test_global_logout_failure_clears_local_session_and_reports_unconfirmed_revocation(): void
    {
        $this->finishFlow($this->flow())->assertRedirect('/dashboard');
        $this->logoutStatus = 503;
        $this->post('/shared/logout')->assertStatus(503)->assertSee('Global sign-out could not be confirmed');
        $this->assertGuest(); $this->assertDatabaseCount('shared_browser_sessions', 0);
    }

}
