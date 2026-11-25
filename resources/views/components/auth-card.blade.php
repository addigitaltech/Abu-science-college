@props(['title', 'lead' => null, 'badge' => null, 'narrow' => false, 'points' => [], 'icon' => 'shield'])
@php
    // Anonymous components do not receive the view composer data, so read the (cached) school settings directly.
    $school = \App\Models\SchoolSettings::current();
    $crest = $school->logo_path ? \App\Support\MediaUrl::for($school->logo_path) : null;
@endphp
<section class="auth-page">
    <div class="auth-shell{{ $narrow ? ' auth-shell--narrow' : '' }}">
        @unless($narrow)
            <aside class="auth-aside" aria-hidden="true">
                @if($crest)<img src="{{ $crest }}" alt="" width="64" height="64">@endif
                <h2>{{ $school->school_name }}</h2>
                @if($school->motto)<p>{{ $school->motto }}</p>@endif
                @if(count($points))
                    <ul>@foreach($points as $point)<li><x-site-icon name="checkCircle" /> {{ $point }}</li>@endforeach</ul>
                @endif
            </aside>
        @endunless
        <div class="auth-main">
            @if($badge)<span class="auth-badge"><x-site-icon :name="$icon" style="width:1rem;height:1rem" /> {{ $badge }}</span>@endif
            <h1 class="auth-title">{{ $title }}</h1>
            @if($lead)<p class="auth-lead">{{ $lead }}</p>@endif
            {{ $slot }}
            @isset($footer)<div class="auth-foot">{{ $footer }}</div>@endisset
            <p class="auth-links"><a href="{{ route('home') }}">&larr; Back to the school website</a></p>
        </div>
    </div>
</section>
