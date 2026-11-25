@extends('site.layout')
@section('title', 'Portal Dashboard | '.$settings->short_name)
@section('content')
@php
    $kindLabel = ['student' => 'Student portal', 'parent' => 'Parent portal', 'teacher' => 'Staff portal', 'admin' => 'Administration', 'account' => 'School portal'][$kind] ?? 'School portal';
    $canAdminConsole = $user->can('manage website content') || $user->can('manage results') || $user->can('manage payments') || $user->can('manage students');
@endphp
@include('portal._topbar', [
    'title' => 'Welcome, '.$user->name,
    'subtitle' => $kindLabel.' · Account: '.ucfirst($kind),
    'showAdmin' => $kind === 'admin' && $canAdminConsole,
    'showPassword' => $kind !== 'student',
])
@if(session('success'))<div class="site-container" style="margin-top:1rem"><div role="status" class="alert alert--ok">{{ session('success') }}</div></div>@endif
<section class="portal-body"><div class="site-container">
@if($kind === 'student')
    @if($profile)
    <div class="panel"><div class="panel__body student-chip">
        @if($profile->photo)<img src="{{ $profile->photo->url() }}" alt="" width="64" height="64">@else<span class="student-chip__ph">{{ strtoupper(substr($profile->first_name, 0, 1)) }}</span>@endif
        <div><strong style="font-size:1.15rem">{{ $profile->fullName() }}</strong><div class="muted" style="font-size:.9rem">{{ $profile->student_number }} &middot; {{ $profile->schoolClass?->name ?? 'Class not assigned' }}@if($profile->arm) ({{ $profile->arm->name }})@endif</div></div>
    </div></div>
    @endif
    <div class="panel">
        <div class="panel__head"><h2>My report cards</h2></div>
        <div class="panel__body">
            @if($reportTerms->isEmpty())
                <p class="empty-state">No results have been published for you yet. They will appear here once the school publishes them.</p>
            @else
                <ul class="report-list">@foreach($reportTerms as $reportTerm)<li><a href="{{ route('reports.show', ['student' => $profile->id, 'term' => $reportTerm->id]) }}" target="_blank" rel="noopener"><span>{{ $reportTerm->session?->name }} &middot; {{ $reportTerm->name }}</span><span class="link-action link-action--blue" style="margin:0">View / print</span></a></li>@endforeach</ul>
            @endif
        </div>
    </div>
@elseif($kind === 'parent')
    @if($children->isEmpty())
        <p class="empty-state">No student profiles are linked to this parent account. Please contact the school office.</p>
    @else
        @foreach($children as $child)
            <div class="panel">
                <div class="panel__head"><div class="student-chip">@if($child->photo)<img src="{{ $child->photo->url() }}" alt="" width="64" height="64">@else<span class="student-chip__ph">{{ strtoupper(substr($child->first_name, 0, 1)) }}</span>@endif<div><h2>{{ $child->fullName() }}</h2><span class="muted" style="font-size:.88rem">{{ $child->student_number }} &middot; {{ $child->schoolClass?->name ?? 'Class not assigned' }}@if($child->arm) ({{ $child->arm->name }})@endif</span></div></div></div>
                <div class="panel__body">
                    @php($terms = $childTerms[$child->id] ?? collect())
                    @if($terms->isEmpty())
                        <p class="muted" style="margin:0">No published results yet.</p>
                    @else
                        <ul class="report-list">@foreach($terms as $reportTerm)<li><a href="{{ route('reports.show', ['student' => $child->id, 'term' => $reportTerm->id]) }}" target="_blank" rel="noopener"><span>{{ $reportTerm->session?->name }} &middot; {{ $reportTerm->name }}</span><span class="link-action link-action--blue" style="margin:0">View / print</span></a></li>@endforeach</ul>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
@elseif($kind === 'teacher')
    <div class="panel">
        <div class="panel__head"><h2>My classes and subjects</h2><a class="btn btn--maroon btn--sm" href="{{ route('staff.results') }}">Manage results</a></div>
        <div class="panel__body">
            @if($assignments->isEmpty())
                <p class="empty-state">No class or subject has been assigned to this account yet. Please contact the school office.</p>
            @else
                <div class="class-grid">
                    @foreach($assignments as $assignment)
                        <a class="panel class-card" style="text-decoration:none;color:inherit;margin:0" href="{{ route('staff.results.subject', $assignment) }}">
                            <h3>{{ $assignment->subject?->name }}</h3>
                            <span class="muted">{{ $assignment->schoolClass?->name }}@if($assignment->arm) &middot; {{ $assignment->arm->name }}@endif</span>
                            <span class="link-action link-action--green" style="margin:.7rem 0 0">Open score sheet &rarr;</span>
                        </a>
                    @endforeach
                </div>
            @endif
            <p style="margin:1.2rem 0 0"><a class="link-more" href="{{ route('staff.results.archive') }}">Reports archive &rarr;</a></p>
        </div>
    </div>
@else
    @if($user->can('publish results'))
    <div class="kpis">
        <div class="kpi"><span class="kpi__icon kpi__icon--gold"><x-site-icon name="clock" /></span><div class="kpi__text"><span>Results awaiting publication</span><strong>{{ $pendingTotal }}</strong></div></div>
    </div>
    @endif
    <div class="panel" style="margin-top:1.1rem">
        <div class="panel__head"><h2>Quick actions</h2></div>
        <div class="panel__body">
            <p class="muted" style="margin-top:0">Tools shown here depend on the permissions granted to your account.</p>
            <div class="action-grid">
                @if($canAdminConsole)<a class="action-tile" href="{{ url('/admin') }}"><span class="kpi__icon"><x-site-icon name="layers" /></span><span>Admin console<small>Students, staff, fees, website</small></span></a>@endif
                @if($user->can('manage results') || $user->can('enter assigned results'))
                    <a class="action-tile" href="{{ route('staff.results') }}"><span class="kpi__icon kpi__icon--green"><x-site-icon name="clipboard" /></span><span>Manage results<small>Enter scores, print reports</small></span></a>
                    <a class="action-tile" href="{{ route('staff.results.archive') }}"><span class="kpi__icon kpi__icon--gold"><x-site-icon name="folder" /></span><span>Reports archive<small>Find any student's report</small></span></a>
                @endif
            </div>
        </div>
    </div>
    @if($activity->isNotEmpty())
    <div class="panel"><div class="panel__head"><h2>Recent activity</h2></div><div class="panel__body"><ul class="activity">@foreach($activity as $event)<li><strong>{{ $event->event }}</strong><time datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->format('M j, Y H:i') }}</time></li>@endforeach</ul></div></div>
    @endif

@endif

@if(in_array($kind, ['student', 'parent'], true))
    @include('portal._fees')
@elseif($kind === 'admin' && $user->can('manage payments') && $payments->isNotEmpty())
    <div class="panel" style="margin-top:1.1rem">
        <div class="panel__head"><h2>Recent payment transactions</h2></div>
        <div class="table-wrap"><table class="table table--stack"><thead><tr><th>Student</th><th>Amount</th><th>Method</th><th>Status</th><th>Receipt</th></tr></thead><tbody>@foreach($payments as $payment)<tr><td>{{ $payment->student?->fullName() }}</td><td>{{ $payment->currency }} {{ number_format((float)$payment->amount,2) }}</td><td>{{ ucfirst($payment->gateway) }}</td><td><span class="pill {{ $payment->status === 'successful' ? 'pill--ok' : ($payment->status === 'pending' ? 'pill--warn' : 'pill--bad') }}">{{ ucfirst($payment->status) }}</span></td><td>{{ $payment->receipt?->receipt_number ?? '—' }}</td></tr>@endforeach</tbody></table></div>
    </div>
@endif
</div></section>
@endsection
