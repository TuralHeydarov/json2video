<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    public function showLogin()
    {
        if (auth()->check() && auth()->user()->is_admin) {
            return redirect('/admin');
        }
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if (config('shared_auth.enabled') && \Illuminate\Support\Facades\DB::table('shared_auth_identities')->where('user_id', Auth::id())->exists()) {
                Auth::logout();
                return back()->withErrors(['email' => 'Use Tural sign-in for this linked account.']);
            }
            if (!auth()->user()->is_admin) {
                Auth::logout();
                return back()->withErrors(['email' => 'Admin access required.']);
            }

            $request->session()->regenerate();
            return redirect()->intended('/admin');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        if (config('shared_auth.enabled')) app(\App\Services\SharedBrowser::class)->clear($request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/admin/login');
    }
}
