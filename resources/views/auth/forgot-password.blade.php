@extends('site.layout')
@section('title', 'Reset password | '.$settings->short_name)
@section('content')
<x-auth-card narrow badge="Account" icon="shield" title="Reset your password" lead="Enter the email address associated with your school account. If reset messages are configured for the account, instructions will be sent.">
    @if(session('status'))<div role="status" class="alert alert--ok" style="margin-bottom:1rem">{{ session('status') }}</div>@endif
    <form method="post" action="{{ route('password.email') }}" class="form-stack">
        @csrf
        <label class="field @error('email') has-error @enderror">Email<input name="email" type="email" value="{{ old('email') }}" required autocomplete="email" inputmode="email" autocapitalize="none" @error('email') aria-invalid="true" @enderror>@error('email')<span class="field__error" role="alert">{{ $message }}</span>@enderror</label>
        <x-turnstile action="password_reset" />
        <button class="btn btn--navy btn--block" type="submit">Request reset link</button>
    </form>
    <p class="auth-links"><a href="{{ route('login') }}">Back to sign in</a></p>
</x-auth-card>
@endsection
