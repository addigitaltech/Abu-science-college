@extends('site.layout')
@section('title', 'Choose a new password | '.$settings->short_name)
@section('content')
<x-auth-card narrow badge="Account" icon="shield" title="Choose a new password" lead="Use at least 12 characters, including upper- and lower-case letters, a number and a symbol.">
    <form method="post" action="{{ route('password.update') }}" class="form-stack">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="field">Email<input name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email" inputmode="email" autocapitalize="none"></label>
        <label class="field">New password<input name="password" type="password" required autocomplete="new-password" minlength="12"></label>
        <label class="field">Confirm password<input name="password_confirmation" type="password" required autocomplete="new-password" minlength="12"></label>
        @if($errors->any())<div role="alert" class="alert alert--error">{{ $errors->first() }}</div>@endif
        <button class="btn btn--navy btn--block" type="submit">Reset password</button>
    </form>
</x-auth-card>
@endsection
