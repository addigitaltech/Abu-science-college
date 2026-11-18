<x-filament-panels::page>
    <style>
        .finance-table-scroll{max-height:min(58vh,640px);overflow:auto;border:1px solid rgba(120,120,120,.22);border-radius:.6rem;overscroll-behavior:contain}
        .finance-table-scroll table{min-width:860px;width:100%;border-collapse:separate;border-spacing:0;font-size:.875rem}
        .finance-table-scroll th{position:sticky;top:0;z-index:2;background:rgb(248 250 252);text-align:left;border-bottom:1px solid rgba(120,120,120,.35)}
        .finance-table-scroll th:first-child,.finance-table-scroll td:first-child{position:sticky;left:0;z-index:1;background:rgb(255 255 255);box-shadow:1px 0 0 rgba(120,120,120,.16)}
        .finance-table-scroll th:first-child{z-index:3;background:rgb(248 250 252)}
        .finance-table-scroll th,.finance-table-scroll td{padding:.55rem .65rem;white-space:nowrap}
        .finance-scroll-hint{display:none;margin:.2rem 0 .5rem;font-size:.75rem;opacity:.72}
        @media(max-width:1279px){.finance-scroll-hint{display:block}}
    </style>
    {{ $this->form }}

    @php($report = $this->report())
    @php($money = fn ($value) => number_format((float) $value, 2))

    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <x-filament::button tag="a" :href="$this->reportUrl()" target="_blank" icon="heroicon-o-printer">Print</x-filament::button>
        <x-filament::button tag="a" :href="$this->reportUrl('pdf')" color="gray" icon="heroicon-o-document-arrow-down">Download PDF</x-filament::button>
        <x-filament::button tag="a" :href="$this->reportUrl('csv')" color="gray" icon="heroicon-o-table-cells">Download spreadsheet</x-filament::button>
    </div>

    <x-filament::section heading="Summary">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem">
            <div><div style="opacity:.7;font-size:.8rem">Verified payments</div><strong style="font-size:1.4rem">{{ $report['totals']['paid_count'] }}</strong></div>
            <div><div style="opacity:.7;font-size:.8rem">Total received</div><strong style="font-size:1.4rem">NGN {{ $money($report['totals']['paid_total']) }}</strong></div>
            <div><div style="opacity:.7;font-size:.8rem">Students still owing</div><strong style="font-size:1.4rem">{{ $report['debtTotals']['students'] }}</strong></div>
            <div><div style="opacity:.7;font-size:.8rem">Total still owing</div><strong style="font-size:1.4rem">NGN {{ $money($report['debtTotals']['balance']) }}</strong></div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Payments" :description="$report['payments']->count().' shown (the printout and spreadsheet list all of them)'">
        <p class="finance-scroll-hint">Scroll within the table to view all payment details; the date and headings stay visible.</p>
        <div class="finance-table-scroll" role="region" aria-label="Payments list; scroll to view all columns" tabindex="0">
            <table>
                <thead><tr style="text-align:left;border-bottom:1px solid rgba(120,120,120,.35)"><th style="padding:.4rem">Date</th><th style="padding:.4rem">Student</th><th style="padding:.4rem">Class</th><th style="padding:.4rem">Method</th><th style="padding:.4rem">Receipt</th><th style="padding:.4rem">Status</th><th style="padding:.4rem;text-align:right">Amount</th></tr></thead>
                <tbody>
                @forelse($report['payments']->sortByDesc('created_at')->take(100) as $payment)
                    <tr style="border-bottom:1px solid rgba(120,120,120,.15)">
                        <td style="padding:.4rem">{{ $payment->created_at?->format('M j, Y g:i A') }}</td>
                        <td style="padding:.4rem">{{ $payment->student?->fullName() }}<div style="opacity:.6;font-size:.75rem">{{ $payment->student?->student_number }}</div></td>
                        <td style="padding:.4rem">{{ $payment->student?->schoolClass?->name }} {{ $payment->student?->arm?->name }}</td>
                        <td style="padding:.4rem">{{ ucfirst($payment->gateway) }}</td>
                        <td style="padding:.4rem">@if($payment->receipt)<a href="{{ route('receipts.show', ['receipt' => $payment->receipt->receipt_number]) }}" target="_blank" style="text-decoration:underline">{{ $payment->receipt->receipt_number }}</a>@else — @endif</td>
                        <td style="padding:.4rem">{{ ucfirst($payment->status) }}</td>
                        <td style="padding:.4rem;text-align:right">NGN {{ $money($payment->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:1rem;opacity:.7">No payments match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Who still owes" :description="'Unpaid and part-paid fees. Total NGN '.$money($report['debtTotals']['balance'])">
        <p class="finance-scroll-hint">Scroll within the table to view all balance details; student names and headings stay visible.</p>
        <div class="finance-table-scroll" role="region" aria-label="Outstanding fees list; scroll to view all columns" tabindex="0">
            <table>
                <thead><tr style="text-align:left;border-bottom:1px solid rgba(120,120,120,.35)"><th style="padding:.4rem">Student</th><th style="padding:.4rem">Class</th><th style="padding:.4rem">Unpaid fees</th><th style="padding:.4rem;text-align:right">Charged</th><th style="padding:.4rem;text-align:right">Paid</th><th style="padding:.4rem;text-align:right">Balance</th></tr></thead>
                <tbody>
                @forelse($report['debts']->take(100) as $row)
                    <tr style="border-bottom:1px solid rgba(120,120,120,.15)">
                        <td style="padding:.4rem">{{ $row['student']?->fullName() }}<div style="opacity:.6;font-size:.75rem">{{ $row['student']?->student_number }}</div></td>
                        <td style="padding:.4rem">{{ $row['class'] }}</td>
                        <td style="padding:.4rem">{{ $row['fees'] }}</td>
                        <td style="padding:.4rem;text-align:right">{{ $money($row['due']) }}</td>
                        <td style="padding:.4rem;text-align:right">{{ $money($row['paid']) }}</td>
                        <td style="padding:.4rem;text-align:right"><strong>{{ $money($row['balance']) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:1rem;opacity:.7">Nobody owes anything for these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
