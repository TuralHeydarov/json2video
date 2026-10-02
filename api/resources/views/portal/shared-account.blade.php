@extends('portal.layouts.app')
@section('title', 'Tural account')
@section('content')
<div class="card" style="max-width:440px;margin:40px auto">
    <div class="card-header"><h3>Tural account</h3></div>
    <div class="card-body">
        <p style="margin-bottom:16px">App account: {{ auth()->user()->email }}</p>
        <p class="text-muted" style="margin-bottom:20px">Your plan, jobs and permissions stay with this existing app account.</p>
        @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
        @if(session()->has('shared_browser'))
            <p style="margin-bottom:16px">Your Tural sign-in is active.</p>
            <form method="POST" action="/shared/logout">@csrf
                <button class="btn btn-primary" type="submit">Sign out of all Tural apps</button>
            </form>
        @else
            <form method="POST" action="/shared/link/start">@csrf
                <div class="form-group"><label for="link-password">Confirm your existing app password</label>
                    <input id="link-password" type="password" name="password" required autocomplete="current-password"></div>
                <button class="btn btn-primary" type="submit">Confirm with Tural</button>
            </form>
        @endif
    </div>
</div>
@endsection
