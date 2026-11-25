@extends('site.layout')
@section('title', 'Student Portal | '.$settings->short_name)
@section('content')
<x-auth-card badge="Student Portal" icon="cap" title="Student sign-in" :lead="\App\Domain\Auth\Support\PortalAccess::studentPinRequired() ? 'Use your Student ID, your surname and the PIN on your PIN slip. No email or password needed.' : 'Use your Student ID and your surname. No email or password needed.'" :points="['See your published report cards', 'Check your fee balance', 'Print your results']">
    <form method="post" action="{{ route('student.login') }}" class="form-stack">
        @csrf
        <label class="field @error('student_id') has-error @enderror">Student ID<input name="student_id" value="{{ old('student_id') }}" required maxlength="60" placeholder="{{ \App\Domain\Identity\StudentIdGenerator::prefix() }}/{{ \App\Domain\Identity\StudentIdGenerator::sessionCode() }}/0001" autocomplete="off" autocapitalize="characters" class="upper-input"></label>
        <label class="field @error('surname') has-error @enderror">Surname (CAPITAL LETTERS)<input name="surname" required maxlength="80" autocomplete="off" autocapitalize="characters" class="upper-input"></label>
        @if(\App\Domain\Auth\Support\PortalAccess::studentPinRequired())
        <label class="field @error('pin') has-error @enderror">PIN (6 digits from your PIN slip)<input name="pin" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" data-no-toggle></label>
        @endif
        @error('student_id')<div class="alert alert--error" role="alert">{{ $message }}</div>@enderror
        @error('surname')<div class="alert alert--error" role="alert">{{ $message }}</div>@enderror
        @error('pin')<div class="alert alert--error" role="alert">{{ $message }}</div>@enderror
        <x-turnstile action="student_login" />
        <button type="submit" class="btn btn--maroon btn--block">Sign in</button>
    </form>
    <x-slot:footer>Cannot sign in? Ask the school office to confirm your Student ID and surname, or to give you a new PIN slip.</x-slot:footer>
</x-auth-card>
@endsection
