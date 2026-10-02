<?php

namespace App\Http\Controllers;

use App\Services\SharedBrowser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SharedAuthController extends Controller
{
    public function start(Request $request, SharedBrowser $browser)
    {
        abort_unless(config('shared_auth.enabled'), 404);
        try {
            return redirect()->away($browser->start($request, $request->query('intent') === 'signup' ? 'signup' : 'login'));
        } catch (\Throwable) { abort(503, 'Tural sign-in unavailable'); }
    }

    public function linkForm(SharedBrowser $browser)
    {
        abort_unless($browser->available(), 404);
        return view('portal.shared-account');
    }

    public function linkStart(Request $request, SharedBrowser $browser)
    {
        abort_unless(config('shared_auth.enabled'), 404);
        $input = $request->validate(['password' => 'required|string|max:4096']);
        abort_if(DB::table('shared_auth_identities')->where('user_id', $request->user()->id)->exists(), 409);
        abort_unless(Auth::validate(['email' => $request->user()->email, 'password' => $input['password']]), 403);
        try { return redirect()->away($browser->start($request, 'link', $request->user()->id)); }
        catch (\Throwable) { abort(503, 'Tural sign-in unavailable'); }
    }

    public function callback(Request $request, SharedBrowser $browser)
    {
        abort_unless(config('shared_auth.enabled'), 404);
        try {
            $state = $request->query('state'); $code = $request->query('code');
            if (!is_string($state) || !is_string($code)) throw new \RuntimeException();
            $browser->finish($request, $state, $code);
            return redirect('/dashboard');
        } catch (\Throwable) {
            return redirect('/login')->withErrors(['shared' => 'Sign-in was not completed. Sign in to your existing app account below to link both accounts, or try Tural sign-in again.']);
        }
    }

    public function logout(Request $request, SharedBrowser $browser)
    {
        abort_unless(config('shared_auth.enabled'), 404);
        if (!$browser->logout($request, true)) {
            return response()->view('portal.shared-logout-failed', [], 503);
        }
        return redirect('/login');
    }
}
