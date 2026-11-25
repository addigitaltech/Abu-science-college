@extends('site.layout')
@section('title', 'Sign in | '.$settings->short_name)
@section('content')
@php
    $iconFor = function ($link) {
        $key = strtolower($link->key.' '.$link->title);
        return match (true) {
            str_contains($key, 'student') => 'cap',
            str_contains($key, 'parent') => 'users',
            str_contains($key, 'staff'), str_contains($key, 'teacher') => 'briefcase',
            str_contains($key, 'result') => 'fileChart',
            str_contains($key, 'admission') => 'userPlus',
            default => 'school',
        };
    };
@endphp
<section class="auth-page" style="align-items:start">
    <div style="width:min(100%,62rem)">
        <div style="text-align:center;margin-bottom:1.6rem">
            <span class="auth-badge"><x-site-icon name="shield" style="width:1rem;height:1rem" /> School portals</span>
            <h1 class="auth-title">Which portal do you need?</h1>
            <p class="auth-lead" style="margin-bottom:0">Each portal has its own sign-in page. Choose yours.</p>
        </div>
        @if(count($portalLinks))
            <div class="portal-choices">
                @foreach($portalLinks as $portalLink)
                    <a class="portal-choice" href="{{ $portalLink->url }}">
                        <span class="portal-choice__icon"><x-site-icon :name="$iconFor($portalLink)" style="width:1.5rem;height:1.5rem" /></span>
                        <h2>{{ $portalLink->title }}</h2>
                        <p>{{ $portalLink->description }}</p>
                        <span class="link-more">Open <x-site-icon name="arrow" /></span>
                    </a>
                @endforeach
            </div>
        @else
            <p class="empty-state">No portal is open right now. Please contact the school office.</p>
        @endif
    </div>
</section>
@endsection
