@php($canSeeAdmin = auth()->user()->can('manage students') || auth()->user()->can('manage results') || auth()->user()->can('manage website content'))
@include('portal._topbar', ['title' => $title, 'subtitle' => $subtitle ?? null, 'showAdmin' => $canSeeAdmin, 'showPassword' => false])
<nav class="portal-nav" aria-label="Staff portal">
    <div class="site-container">
        <ul>
            <li><a href="{{ route('portal.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('staff.results') }}"@if(($current ?? '') === 'results') aria-current="page"@endif>Manage results</a></li>
            <li><a href="{{ route('staff.results.archive') }}"@if(($current ?? '') === 'archive') aria-current="page"@endif>Reports archive</a></li>
            <li><a href="{{ route('staff.results.broadsheet') }}"@if(($current ?? '') === 'broadsheet') aria-current="page"@endif>Class broadsheet</a></li>
        </ul>
    </div>
</nav>
@if($errors->any())
    <div class="site-container" style="margin-top:1rem"><div role="alert" class="alert alert--error">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div></div>
@endif
