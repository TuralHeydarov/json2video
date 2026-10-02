@extends('portal.layouts.app')
@section('title', 'Sign-out status')
@section('content')
<div class="card" style="max-width:440px;margin:40px auto"><div class="card-body">
    <p class="alert alert-error">Local sign-out completed. Global sign-out could not be confirmed.</p>
    <a href="/login">Return to sign-in</a>
</div></div>
@endsection
