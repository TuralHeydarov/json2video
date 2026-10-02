<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showRegister()
    {
        if (auth()->check())
            return redirect('/dashboard');
        if (config('shared_auth.enabled')) return redirect('/shared/start?intent=signup');
        return view('portal.register');
    }

    public function register(Request $request)
    {
        abort_if(config('shared_auth.enabled'), 403, 'Create your account through Tural sign-in');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Assign Free plan by default
        $freePlan = Plan::where('slug', 'free')->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => bcrypt($validated['password']),
            'plan_id' => $freePlan?->id,
        ]);

        // Generate API key for new user
        $rawKey = 'j2v_' . Str::random(32);
        ApiKey::create([
            'user_id' => $user->id,
            'key_hash' => hash('sha256', $rawKey),
            'key_prefix' => substr($rawKey, 0, 8),
            'label' => 'Default Key',
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        // Flash the API key so user can see it once
        return redirect('/dashboard')->with('api_key', $rawKey);
    }

    public function showLogin()
    {
        if (auth()->check())
            return redirect('/dashboard');
        return view('portal.login');
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
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        if (config('shared_auth.enabled')) app(\App\Services\SharedBrowser::class)->clear($request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
