<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SharedSessionExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $expires = $request->session()->get('shared_auth_expires_at');
        if ($expires !== null && (!is_numeric($expires) || time() >= (int) $expires)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/login');
        }
        return $next($request);
    }
}
