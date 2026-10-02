<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SharedAuth
{
    public function claims(string $token): object
    {
        $issuer = rtrim(config('shared_auth.issuer'), '/');
        $url = parse_url($issuer);
        if (($url['scheme'] ?? '') !== 'https' || isset($url['query']) || isset($url['fragment'])
            || isset($url['user']) || isset($url['pass']) || strlen($token) > 16384) {
            throw new RuntimeException('Invalid shared authentication configuration');
        }
        // This endpoint is configured, never discovered from the token's iss/jku.
        $response = Http::timeout(5)->withoutRedirecting()->get($issuer.'/.well-known/jwks.json');
        if (!$response->successful() || strlen($response->body()) > 65536) {
            throw new RuntimeException('Shared authentication unavailable');
        }
        $keys = array_values(array_filter($response->json('keys') ?? [], fn ($key) =>
            ($key['alg'] ?? '') === 'ES256' && ($key['kty'] ?? '') === 'EC'
            && ($key['crv'] ?? '') === 'P-256' && ($key['use'] ?? '') === 'sig'));
        $claims = JWT::decode($token, JWK::parseKeySet(['keys' => $keys]));
        $audience = (array) ($claims->aud ?? []);
        if (($claims->iss ?? '') !== $issuer || !in_array('authenticated', $audience, true)
            || !is_int($claims->exp ?? null)
            || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $claims->sub ?? '')
            || ($claims->role ?? '') !== 'authenticated' || ($claims->is_anonymous ?? false)) {
            throw new RuntimeException('Invalid shared identity');
        }
        return $claims;
    }
}
