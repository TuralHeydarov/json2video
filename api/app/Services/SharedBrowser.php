<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SharedBrowser
{
    public function __construct(private SharedAuth $auth) {}

    private function settings(): void
    {
        $origin = rtrim(config('app.url'), '/');
        $parsed = parse_url($origin);
        $issuer = parse_url(config('shared_auth.issuer'));
        if (!config('shared_auth.enabled') || ($parsed['scheme'] ?? '') !== 'https'
            || ($issuer['scheme'] ?? '') !== 'https' || isset($issuer['query']) || isset($issuer['fragment'])
            || isset($issuer['user']) || isset($issuer['pass'])
            || isset($parsed['query']) || isset($parsed['fragment']) || isset($parsed['user'])
            || isset($parsed['pass']) || (($parsed['path'] ?? '') !== '')
            || config('shared_auth.callback') !== $origin.'/shared/callback'
            || !Str::isUuid(config('shared_auth.client_id')) || !config('shared_auth.client_secret')
            || config('session.driver') === 'cookie'
            || (config('session.driver') === 'array' && !app()->environment('testing'))
            || config('session.domain') !== null || !config('session.secure')
            || !config('session.http_only') || config('session.same_site') !== 'lax'
            || config('session.path') !== '/' || config('session.cookie') !== '__Host-json2video-session') {
            throw new RuntimeException('Shared browser configuration unavailable');
        }
    }

    public function available(): bool
    {
        try { $this->settings(); return true; } catch (\Throwable) { return false; }
    }

    private function random(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function save(array $data, int $seconds): string
    {
        DB::table('shared_browser_sessions')->where('expires_at', '<=', now())->delete();
        $raw = $this->random();
        DB::table('shared_browser_sessions')->insert([
            'id' => hash('sha256', $raw), 'payload' => Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)),
            'expires_at' => now()->addSeconds($seconds),
        ]);
        return $raw;
    }

    private function row(string $raw, bool $lock = false): object
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{43}$/', $raw)) throw new RuntimeException('Invalid browser session');
        $query = DB::table('shared_browser_sessions')->where('id', hash('sha256', $raw))->where('expires_at', '>', now());
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        if (!$row) throw new RuntimeException('Expired browser session');
        return $row;
    }

    private function data(object $row): array
    {
        return json_decode(Crypt::decryptString($row->payload), true, 16, JSON_THROW_ON_ERROR);
    }

    public function start(Request $request, string $intent, ?int $legacyId = null): string
    {
        $this->settings();
        if (!in_array($intent, ['login', 'signup', 'link'], true) || ($intent === 'link' && !$legacyId)) {
            throw new RuntimeException('Legacy ownership proof required');
        }
        $state = $this->random(); $verifier = $this->random(); $nonce = $this->random();
        $old = $request->session()->get('shared_pending');
        if (is_string($old)) DB::table('shared_browser_sessions')->where('id', hash('sha256', $old))->delete();
        $request->session()->put('shared_pending', $this->save([
            'kind' => 'pending', 'state' => $state, 'verifier' => $verifier, 'nonce' => $nonce,
            'intent' => $intent, 'legacy_id' => $legacyId,
        ], 600));
        return rtrim(config('shared_auth.issuer'), '/').'/oauth/authorize?'.http_build_query([
            'client_id' => config('shared_auth.client_id'), 'redirect_uri' => config('shared_auth.callback'),
            'response_type' => 'code', 'scope' => 'openid email profile', 'state' => $state, 'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    private function exchange(array $body): array
    {
        $this->settings();
        $response = Http::timeout(10)->withoutRedirecting()->asForm()
            ->withBasicAuth(config('shared_auth.client_id'), config('shared_auth.client_secret'))
            ->post(rtrim(config('shared_auth.issuer'), '/').'/oauth/token', $body);
        if (!$response->successful() || strlen($response->body()) > 65536) {
            throw new RuntimeException('Shared token exchange failed');
        }
        $tokens = $response->json();
        foreach (['access_token', 'refresh_token'] as $key) {
            if (!is_string($tokens[$key] ?? null) || strlen($tokens[$key]) > 16384) {
                throw new RuntimeException('Invalid token response');
            }
        }
        return $tokens;
    }

    public function finish(Request $request, string $state, string $code): void
    {
        $this->settings();
        if (!preg_match('/^[a-zA-Z0-9_-]{43}$/', $state) || !$code || strlen($code) > 4096) {
            throw new RuntimeException('Invalid callback');
        }
        $raw = $request->session()->pull('shared_pending');
        if (!is_string($raw)) throw new RuntimeException('Missing callback session');
        $data = DB::transaction(function () use ($raw, $state) {
            $row = $this->row($raw, true); $data = $this->data($row);
            if (($data['kind'] ?? '') !== 'pending' || !hash_equals($data['state'], $state)) {
                throw new RuntimeException('Callback state mismatch');
            }
            DB::table('shared_browser_sessions')->where('id', $row->id)->delete();
            return $data;
        });
        $tokens = $this->exchange(['grant_type' => 'authorization_code', 'code' => $code,
            'code_verifier' => $data['verifier'], 'redirect_uri' => config('shared_auth.callback')]);
        $claims = $this->auth->claims($tokens['access_token']);
        $identity = $this->auth->identity($tokens['id_token'] ?? '');
        if ($identity->sub !== $claims->sub || ($identity->nonce ?? '') !== $data['nonce']) {
            throw new RuntimeException('Identity token mismatch');
        }
        [$user, $apiKey] = DB::transaction(function () use ($data, $claims, $identity) {
            $binding = DB::table('shared_auth_identities')->where('issuer', $claims->iss)->where('subject', $claims->sub)->first();
            if ($data['intent'] === 'link') {
                $old = DB::table('shared_auth_identities')->where('user_id', $data['legacy_id'])->first();
                if ($binding || $old) {
                    if (!$binding || !$old || $binding->user_id !== $data['legacy_id']
                        || $old->issuer !== $claims->iss || $old->subject !== $claims->sub) {
                        throw new RuntimeException('Binding already exists');
                    }
                } else {
                    User::findOrFail($data['legacy_id']);
                    $this->bind($data['legacy_id'], $claims);
                }
                return [User::findOrFail($data['legacy_id']), null];
            }
            if ($binding) return [User::findOrFail($binding->user_id), null];
            if ($data['intent'] !== 'signup' || ($identity->email_verified ?? false) !== true
                || !is_string($identity->email ?? null) || strlen($identity->email) > 255
                || !filter_var($identity->email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Create or link your app account first');
            }
            $email = strtolower(trim($identity->email));
            if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw new RuntimeException('Link your existing account');
            }
            $user = User::create(['name' => is_string($identity->name ?? null) ? substr($identity->name, 0, 255) : $email,
                'email' => $email, 'password' => Str::random(128), 'is_admin' => false,
                'plan_id' => Plan::where('slug', 'free')->value('id')]);
            $user->email_verified_at = now(); $user->save();
            $this->bind($user->id, $claims);
            $rawKey = 'j2v_'.Str::random(32);
            ApiKey::create(['user_id' => $user->id, 'key_hash' => hash('sha256', $rawKey),
                'key_prefix' => substr($rawKey, 0, 8), 'label' => 'Default Key', 'is_active' => true]);
            return [$user, $rawKey];
        });
        $this->clear($request);
        $session = $this->save(['kind' => 'session', 'user_id' => $user->id,
            'access' => $tokens['access_token'], 'refresh' => $tokens['refresh_token'],
            'subject' => $claims->sub, 'session_id' => $claims->session_id], 30 * 86400);
        Auth::login($user, false);
        $request->session()->regenerate(); $request->session()->put('shared_browser', $session);
        if ($apiKey) $request->session()->flash('api_key', $apiKey);
    }

    private function bind(int $id, object $claims): void
    {
        DB::table('shared_auth_identities')->insert(['user_id' => $id, 'issuer' => $claims->iss,
            'subject' => $claims->sub, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function resolve(Request $request): array
    {
        $this->settings();
        $raw = $request->session()->get('shared_browser');
        if (!is_string($raw)) throw new RuntimeException('Shared session required');
        // Row locking serializes refresh across all PHP workers.
        return DB::transaction(function () use ($raw, $request) {
            $row = $this->row($raw, true); $data = $this->data($row);
            if (($data['kind'] ?? '') !== 'session') throw new RuntimeException('Invalid session');
            $part = explode('.', $data['access'])[1] ?? '';
            $payload = json_decode(base64_decode(strtr($part, '-_', '+/')), true);
            if (($payload['exp'] ?? 0) <= time() + 30) {
                $tokens = $this->exchange(['grant_type' => 'refresh_token', 'refresh_token' => $data['refresh']]);
                $claims = $this->auth->claims($tokens['access_token']);
                if ($claims->sub !== $data['subject'] || $claims->session_id !== $data['session_id']) {
                    throw new RuntimeException('Refresh identity mismatch');
                }
                $data['access'] = $tokens['access_token']; $data['refresh'] = $tokens['refresh_token'];
                DB::table('shared_browser_sessions')->where('id', $row->id)
                    ->update(['payload' => Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR))]);
            }
            $claims = $this->auth->claims($data['access']);
            $bound = DB::table('shared_auth_identities')->where('issuer', $claims->iss)->where('subject', $claims->sub)->value('user_id');
            if ($claims->sub !== $data['subject'] || $claims->session_id !== $data['session_id']
                || $bound !== $data['user_id'] || $request->user()?->id !== $bound) {
                throw new RuntimeException('Application binding changed');
            }
            return $data;
        });
    }

    public function clear(Request $request): void
    {
        $raw = $request->session()->pull('shared_browser');
        if (is_string($raw)) DB::table('shared_browser_sessions')->where('id', hash('sha256', $raw))->delete();
    }

    public function logout(Request $request, bool $global = false): bool
    {
        $confirmed = true;
        try {
            if ($global) {
                $data = $this->resolve($request);
                $response = Http::timeout(10)->withoutRedirecting()->withToken($data['access'])
                    ->post(rtrim(config('shared_auth.issuer'), '/').'/logout?scope=global');
                $confirmed = $response->successful();
            }
        } catch (\Throwable) { $confirmed = false; }
        finally {
            try { $this->clear($request); } catch (\Throwable) { $confirmed = false; }
            Auth::logout();
            $request->session()->invalidate(); $request->session()->regenerateToken();
        }
        return $confirmed;
    }
}
