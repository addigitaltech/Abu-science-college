@php
    $money = fn ($value) => 'NGN '.number_format((float) $value, 2);
    $byStudent = $fees->groupBy('student_id');
    $grandBalance = $fees->sum(fn ($fee) => $fee->balance());
@endphp
<section style="margin-top:2rem" id="fees">
    <h2 class="font-display text-2xl font-semibold text-[var(--brand-800)]">School fees</h2>

    @if($errors->has('payment') || $errors->has('amount'))
        <div class="alert alert--error" style="margin-top:1rem" role="alert">{{ $errors->first('payment') ?: $errors->first('amount') }}</div>
    @endif

    @if($fees->isEmpty())
        <p class="empty-state" style="margin-top:1rem">Nothing is owing: there are no unpaid fees for this account.</p>
    @else
        <div class="kpis" style="margin-top:1rem">
            <div class="kpi" style="grid-column:1/-1">
                <span class="kpi__icon kpi__icon--gold"><x-site-icon name="fileChart" /></span>
                <div class="kpi__text">
                    <span>Total still owing</span>
                    <strong>{{ $money($grandBalance) }}</strong>
                    <small class="muted" style="display:block;margin-top:.25rem;font-size:.8rem;font-weight:400">@if($paymentMode === 'full')The school takes all fees of a student together in one payment.@else You may pay a fee in full or in installments (smallest payment {{ $money($minInstallment) }}, or the balance if it is less).@endif</small>
                </div>
            </div>
        </div>

        @foreach($byStudent as $studentId => $studentFees)
            @php($student = $studentFees->first()->student)
            @php($studentBalance = $studentFees->sum(fn ($fee) => $fee->balance()))
            <div class="panel" style="margin-top:1.1rem">
                <div class="panel__head">
                    <span><strong>{{ $student?->fullName() }}</strong> <span class="muted">({{ $student?->student_number }})</span></span>
                    <span>Owes <strong>{{ $money($studentBalance) }}</strong></span>
                </div>
                <div class="table-wrap">
                    <table class="table table--stack">
                        <thead><tr><th>Fee</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Due date</th>@if($paymentMode === 'installment')<th>Pay</th>@endif</tr></thead>
                        <tbody>
                        @foreach($studentFees as $fee)
                            <tr>
                                <td>{{ $fee->feeType?->name ?? 'School fee' }}@if($fee->description)<span class="muted" style="display:block;font-size:.78rem">{{ $fee->description }}</span>@endif @if($fee->status === 'partial')<span class="pill pill--warn">Part paid</span>@elseif($fee->status === 'overdue')<span class="pill pill--bad">Overdue</span>@endif</td>
                                <td>{{ $money($fee->amount_due) }}</td>
                                <td>{{ $money($fee->amount_paid) }}</td>
                                <td><strong>{{ $money($fee->balance()) }}</strong></td>
                                <td>{{ $fee->due_date?->format('M j, Y') ?? '—' }}</td>
                                @if($paymentMode === 'installment')
                                    <td data-label="Pay">
                                        @if($gatewayOptions->isEmpty())
                                            <span class="muted" style="font-size:.8rem">Online checkout is not set up. Contact the school office.</span>
                                        @else
                                            <form method="post" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:.5rem;width:100%" data-confirm="Continue to the secure checkout to pay this school fee?">
                                                @csrf
                                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                <input type="text" inputmode="decimal" name="amount" value="{{ number_format($fee->balance(), 2, '.', '') }}" pattern="\d{1,9}(\.\d{1,2})?" aria-label="Amount to pay in naira" class="sheet-input" style="width:7.5rem;text-align:right">
                                                @foreach($gatewayOptions as $gateway)
                                                    <button formaction="{{ route('payments.checkout', ['assignment' => $fee->id, 'gateway' => $gateway['driver']]) }}" class="btn btn--maroon btn--sm">Pay with {{ $gateway['name'] }}</button>
                                                @endforeach
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if($paymentMode === 'full')
                    <div class="panel__body" style="border-top:1px solid var(--ui-line);display:flex;flex-wrap:wrap;gap:.6rem">
                        @forelse($gatewayOptions as $gateway)
                            <form method="post" action="{{ route('payments.checkout', ['assignment' => $studentFees->first()->id, 'gateway' => $gateway['driver']]) }}" data-confirm="Pay all {{ $money($studentBalance) }} owing for this student?">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <button class="btn btn--maroon">Pay all {{ $money($studentBalance) }} with {{ $gateway['name'] }}</button>
                            </form>
                        @empty
                            <span class="muted" style="font-size:.8rem">Online checkout is not set up. Contact the school office.</span>
                        @endforelse
                    </div>
                @endif
            </div>
        @endforeach
    @endif

    @if($payments->isNotEmpty())
        <h3 class="font-display text-xl font-semibold text-[var(--brand-800)]" style="margin-top:1.8rem">Payments made</h3>
        <div class="panel" style="margin-top:.8rem">
            <div class="table-wrap">
                <table class="table table--stack">
                    <thead><tr>@if($kind === 'parent')<th>Student</th>@endif<th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Receipt</th></tr></thead>
                    <tbody>
                    @foreach($payments as $payment)
                        <tr>
                            @if($kind === 'parent')<td>{{ $payment->student?->fullName() }}</td>@endif
                            <td>{{ $payment->created_at?->format('M j, Y') }}</td>
                            <td>{{ $money($payment->amount) }}</td>
                            <td>{{ ucfirst($payment->gateway) }}</td>
                            <td><span class="pill {{ $payment->status === 'successful' ? 'pill--ok' : ($payment->status === 'pending' ? 'pill--warn' : 'pill--bad') }}">{{ $payment->status === 'successful' ? 'Verified' : ($payment->status === 'pending' ? 'Awaiting verification' : ucfirst($payment->status)) }}</span></td>
                            <td>@if($payment->receipt && $payment->status === 'successful')<a class="link-action link-action--blue" style="margin:0" href="{{ route('receipts.show', ['receipt' => $payment->receipt->receipt_number]) }}" target="_blank" rel="noopener">{{ $payment->receipt->receipt_number }} (print)</a>@else — @endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
