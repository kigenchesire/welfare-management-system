<table style="width:100%; margin-bottom: 18px;">
    <tr>
        <td style="vertical-align: top;">
            <div style="font-size: 18px; font-weight: bold;">{{ $s['group_name'] }}</div>
            <div style="font-size: 12px; color: #666;">{{ $s['address'] }}<br>{{ $s['phone'] }} &middot; {{ $s['email'] }}</div>
        </td>
        <td style="text-align: right; vertical-align: top;">
            <div style="font-size: 16px; font-weight: bold;">PAYMENT RECEIPT</div>
            <div style="font-size: 12px;">No. {{ $payment->receipt_no }}</div>
            @if($payment->status === 'reversed')
                <div style="color: #b02a37; font-weight: bold;">REVERSED</div>
            @endif
        </td>
    </tr>
</table>
<table style="width:100%; border-collapse: collapse; font-size: 13px;" class="table">
    <tr><td style="padding:6px 0; color:#666; width: 40%;">Received from</td><td style="padding:6px 0;"><strong>{{ $payment->member->full_name }}</strong></td></tr>
    <tr><td style="padding:6px 0; color:#666;">Member No / National ID</td><td style="padding:6px 0;">{{ $payment->member->member_no }} / {{ $payment->member->national_id }}</td></tr>
    <tr><td style="padding:6px 0; color:#666;">Amount</td><td style="padding:6px 0; font-size: 16px;"><strong>{{ money($payment->amount) }}</strong></td></tr>
    @if($payment->transaction_cost !== null)<tr><td style="padding:6px 0; color:#666;">Transaction cost</td><td style="padding:6px 0;">{{ money($payment->transaction_cost) }} <span style="color:#888;">(not credited to wallet)</span></td></tr>@endif
    @if($payment->welfareCase)<tr><td style="padding:6px 0; color:#666;">Paid towards case</td><td style="padding:6px 0;">{{ $payment->welfareCase->case_no }} ({{ $payment->welfareCase->deceased_name }})</td></tr>@endif
    <tr><td style="padding:6px 0; color:#666;">Payment method</td><td style="padding:6px 0;">{{ $payment->method }}</td></tr>
    <tr><td style="padding:6px 0; color:#666;">Transaction reference</td><td style="padding:6px 0;">{{ $payment->reference }}</td></tr>
    <tr><td style="padding:6px 0; color:#666;">Payment date</td><td style="padding:6px 0;">{{ $payment->payment_date->format('d M Y') }}</td></tr>
    @if($payment->remarks)<tr><td style="padding:6px 0; color:#666;">Remarks</td><td style="padding:6px 0;">{{ $payment->remarks }}</td></tr>@endif
    <tr><td style="padding:6px 0; color:#666;">Recorded by</td><td style="padding:6px 0;">{{ $payment->recorder?->name }} on {{ $payment->created_at->format('d M Y H:i') }}</td></tr>
    @if($payment->status === 'reversed')
        <tr><td style="padding:6px 0; color:#666;">Reversal</td><td style="padding:6px 0;">{{ $payment->reversed_at?->format('d M Y') }}: {{ $payment->reversal_reason }}</td></tr>
    @endif
</table>
<p style="font-size: 11px; color: #888; margin-top: 18px;">Thank you. This receipt confirms money credited to your welfare wallet.</p>
