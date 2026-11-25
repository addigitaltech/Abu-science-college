@extends('site.layout')
@section('title', 'Parent Portal | '.$settings->short_name)
@section('content')
<x-auth-card badge="Parent Portal" icon="users" title="Parent sign-in" lead="Sign in with the email address you gave the school to see your children's results and fees." :points="['View published report cards', 'Check fees and payment history', 'Print receipts']">
    <form method="post" action="{{ route('login.parent.submit') }}" class="form-stack">
        @csrf
        <label class="field @error('email') has-error @enderror">Email<input name="email" type="email" value="{{ old('email') }}" required maxlength="190" autocomplete="username" inputmode="email" autocapitalize="none" @error('email') aria-invalid="true" @enderror>@error('email')<span class="field__error" role="alert">{{ $message }}</span>@enderror</label>
        <label class="field @error('password') has-error @enderror">Password<input name="password" type="password" required autocomplete="current-password" @error('password') aria-invalid="true" @enderror>@error('password')<span class="field__error" role="alert">{{ $message }}</span>@enderror</label>
        <label class="check-row"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
        <x-turnstile action="portal_login" />
        <button type="submit" class="btn btn--navy btn--block">Sign in</button>
    </form>
    <p class="auth-links"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
</x-auth-card>
@endsection
