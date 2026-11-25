{{-- Shared portal header: who/where on the left, search + account menu on the right (one tidy control on phones). --}}
@php
    // Included (not a component): $title is required; the rest are optional.
    $subtitle = $subtitle ?? null;
    $showAdmin = $showAdmin ?? false;
    $showPassword = $showPassword ?? true;
@endphp
@php($authUser = auth()->user())
<section class="portal-head">
    <div class="site-container portal-head__inner">
        <div class="portal-head__who">
            <span class="portal-head__crest" aria-hidden="true">{{ strtoupper(mb_substr((string) $authUser?->name, 0, 1)) }}</span>
            <div style="min-width:0">
                <h1>{{ $title }}</h1>
                @if(filled($subtitle))<p>{{ $subtitle }}</p>@endif
            </div>
        </div>
        <div class="portal-tools">
            <a class="icon-btn" href="{{ route('portal.search') }}" aria-label="Search the portal" title="Search the portal"><x-site-icon name="search" /></a>
            <details class="user-menu">
                <summary aria-label="Account menu">
                    <span class="user-menu__avatar" aria-hidden="true">{{ strtoupper(mb_substr((string) $authUser?->name, 0, 1)) }}</span>
                    <span class="user-menu__name">{{ $authUser?->name }}</span>
                    <x-site-icon name="chevron" style="width:.85rem;height:.85rem" />
                </summary>
                <div class="user-menu__panel">
                    <div class="user-menu__who"><strong>{{ $authUser?->name }}</strong>{{ $authUser?->email }}</div>
                    <a href="{{ route('portal.dashboard') }}"><x-site-icon name="school" /> Dashboard</a>
                    @if($showAdmin)<a href="{{ url('/admin') }}"><x-site-icon name="layers" /> Admin console</a>@endif
                    @if($showPassword)<a href="{{ route('account.password') }}"><x-site-icon name="shield" /> Change password</a>@endif
                    <hr>
                    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" data-no-loading><x-site-icon name="arrow" /> Sign out</button></form>
                </div>
            </details>
        </div>
    </div>
</section>
