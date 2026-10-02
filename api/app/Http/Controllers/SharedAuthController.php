<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SharedAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;

class SharedAuthController extends Controller
{
    public function login(Request $request, SharedAuth $auth)
    {
        abort_unless(config('shared_auth.enabled'), 404);
        $input = $request->validate(['access_token' => 'required|string|max:16384']);
        try {
            $claims = $auth->claims($input['access_token']);
        } catch (\Throwable $exception) {
            abort(401, 'Invalid shared session');
        }
        $identity = DB::table('shared_auth_identities')->where('issuer', $claims->iss)
            ->where('subject', $claims->sub)->first();
        abort_unless($identity, 403, 'Link both accounts before using shared sign-in');
        $user = User::findOrFail($identity->user_id);
        // App permissions and plans are read from its existing user record.
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('shared_auth_expires_at', $claims->exp);
        return redirect('/dashboard');
    }

    public function link(Request $request, SharedAuth $auth)
    {
        abort_unless(config('shared_auth.enabled'), 404);
        // A fresh legacy password proves ownership, in addition to CSRF and the
        // existing app session. Matching emails alone never establishes a link.
        $input = $request->validate(['access_token' => 'required|string|max:16384', 'password' => 'required|string']);
        abort_unless(Auth::validate(['email' => $request->user()->email, 'password' => $input['password']]), 403);
        try {
            $claims = $auth->claims($input['access_token']);
        } catch (\Throwable $exception) {
            abort(401, 'Invalid shared session');
        }
        try {
            $same = DB::transaction(function () use ($request, $claims) {
            $existing = DB::table('shared_auth_identities')->where('user_id', $request->user()->id)
                ->orWhere(fn ($query) => $query->where('issuer', $claims->iss)->where('subject', $claims->sub))->first();
            if ($existing) {
                return $existing->user_id === $request->user()->id
                    && $existing->issuer === $claims->iss && $existing->subject === $claims->sub;
            }
            DB::table('shared_auth_identities')->insert([
                'user_id' => $request->user()->id, 'issuer' => $claims->iss, 'subject' => $claims->sub,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return true;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent link must never replace either account's binding.
            abort(409, 'Account binding already exists');
        }
        abort_unless($same, 409, 'Account binding already exists');
        return response()->json(['linked' => true]);
    }
}
