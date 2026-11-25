@extends('site.layout')
@section('title', 'Staff Portal | '.$settings->short_name)
@section('content')
<x-auth-card badge="Staff Portal" icon="briefcase" title="Staff sign-in" lead="For teachers and school staff: enter scores, view classes and print reports." :points="['Enter CA and exam scores', 'See your classes and subjects', 'Print and review reports']">
    <form method="post" action="{{ route('login.staff.submit') }}" class="form-stack">
        @csrf
        <label class="field @error('email') has-error @enderror">Email<input name="email" type="email" value="{{ old('email') }}" required maxlength="190" autocomplete="username" inputmode="email" autocapitalize="none" @error('email') aria-invalid="true" @enderror>@error('email')<span class="field__error" role="alert">{{ $message }}</span>@enderror</label>
        <label class="field @error('password') has-error @enderror">Password<input name="password" type="password" required autocomplete="current-password" @error('password') aria-invalid="true" @enderror>@error('password')<span class="field__error" role="alert">{{ $message }}</span>@enderror</label>
        <label class="check-row"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
        <x-turnstile action="portal_login" />
        <button type="submit" class="btn btn--navy btn--block">Sign in</button>
    </form>
    <p class="auth-links"><a href="{{ route('password.request') }}">Forgot your password?</a>@if($settings->show_admin_link)<a href="{{ url('/admin') }}">Administrator? Use the admin login</a>@endif</p>
</x-auth-card>
@endsection
