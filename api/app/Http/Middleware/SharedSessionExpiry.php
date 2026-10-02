<?php

namespace App\Http\Middleware;

use App\Services\SharedBrowser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SharedSessionExpiry
{
    public function handle(Request $request, Closure $next)
    {
        if (config('shared_auth.enabled') && Auth::check()) {
            $linked = DB::table('shared_auth_identities')->where('user_id', Auth::id())->exists();
            if ($linked) {
                try { app(SharedBrowser::class)->resolve($request); }
                catch (\Throwable) {
                    app(SharedBrowser::class)->clear($request); Auth::logout();
                    $request->session()->invalidate(); $request->session()->regenerateToken();
                    return redirect('/login')->withErrors(['shared' => 'Use Tural sign-in for your linked account.']);
                }
            }
        }
        $response = $next($request);
        if ($request->is('shared/*')) {
            $response->headers->set('Cache-Control', 'no-store');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }
        return $response;
    }
}
