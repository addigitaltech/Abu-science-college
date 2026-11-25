@extends('site.layout')
@section('title', 'Portal unavailable | '.$settings->short_name)
@section('content')
<x-auth-card narrow badge="Portal" icon="info" :title="'The '.$portal.' portal is switched off'" :lead="'The school has temporarily closed this portal.'.(\App\Domain\Auth\Support\PortalAccess::checkerEnabled() ? ' You can still check results with your Student ID and surname.' : ' Please contact the school office.')">
    <div style="display:grid;gap:.7rem">
        @if(\App\Domain\Auth\Support\PortalAccess::checkerEnabled())<a class="btn btn--maroon" href="{{ route('result-check') }}">Check Result</a>@endif
        <a class="btn btn--outline" href="{{ route('home') }}">Back to the home page</a>
    </div>
</x-auth-card>
@endsection
