@extends('portal.layouts.app')
@section('title', 'Login')

@section('content')
    <div style="display:flex; align-items:center; justify-content:center; min-height: calc(100vh - 65px); padding: 40px 20px;
        background-image: radial-gradient(ellipse at 30% 20%, rgba(100,255,218,0.04) 0%, transparent 50%),
                           radial-gradient(ellipse at 70% 80%, rgba(99,102,241,0.04) 0%, transparent 50%);">
        <div class="card" style="width: 100%; max-width: 400px;">
            <div class="card-header">
                <h3>Welcome Back</h3>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-error">{{ $errors->first() }}</div>
                @endif
                @if(app(\App\Services\SharedBrowser::class)->available())
                    <a href="/shared/start" class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:12px">Continue with Tural</a>
                    <p class="text-muted" style="font-size:13px;margin-bottom:20px;text-align:center">Already use this app? Sign in below, then link your Tural account.</p>
                @endif
                <form method="POST" action="/login">
                    @csrf
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Sign
                        In</button>
                </form>
                <p class="text-muted mt-4" style="text-align:center; font-size:13px;">Don't have an account? <a
                        href="/register">Create one free</a></p>
            </div>
        </div>
    </div>
@endsection