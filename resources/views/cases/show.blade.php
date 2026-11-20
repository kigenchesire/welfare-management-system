@extends('layouts.app')
@section('title', 'Case '.$case->case_no)
@section('subtitle')
    {{ $case->deceased_name }} &middot; {{ $case->category_label }} &middot; <span class="badge {{ status_badge($case->status) }}">{{ $case->status_label }}</span>
@endsection

@php
    $canPay = $case->status !== 'cancelled' && auth()->user()->can('deposits.record');
    $canRemind = $case->status !== 'cancelled' && (auth()->user()->can('cases.manage') || auth()->user()->can('deposits.record'));
    $canDisburse = $case->status !== 'cancelled' && auth()->user()->can('disbursements.manage');
    $perMember = \App\Models\WelfareCase::split((float) $case->contribution_amount, (float) $case->office_share, (float) $case->transport_share);
    $unpaid = $invoices->filter(fn ($i) => in_array($i->status, ['unpaid', 'partially_paid'], true) && $i->outstanding > 0);
@endphp
@section('actions')
    @if($canPay)
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#payModal">Record payment</button>
    @endif
    @if($canRemind && $unpaid->isNotEmpty())
        <form method="POST" action="{{ route('cases.remind', $case) }}" data-confirm="Send an SMS reminder to {{ $unpaid->count() }} member(s) who have not fully paid, each with the amount they owe ({{ money($unpaid->sum(fn ($i) => $i->outstanding)) }} in total)?">
            @csrf @include('partials.submission')
            <button class="btn btn-outline-warning">Remind unpaid ({{ $unpaid->count() }})</button>
        </form>
    @endif
    @can('reports.welfare')
        <a href="{{ route('reports.show', ['case-contributions', 'case_id' => $case->id]) }}" class="btn btn-outline-primary">Contribution report</a>
    @endcan
    @can('cases.manage')
        @if($case->isActive())
            <form method="POST" action="{{ route('cases.process', $case) }}" data-confirm="Raise invoices for any active members who have not yet been invoiced for this case?">
                @csrf @include('partials.submission')
                <button class="btn btn-outline-primary">Invoice new members</button>
            </form>
            <form method="POST" action="{{ route('cases.close', $case) }}" data-confirm="Close this case? Outstanding invoices remain payable.">
                @csrf @include('partials.submission')
                <button class="btn btn-outline-secondary">Close case</button>
            </form>
        @endif
        @if($case->status !== 'cancelled')
            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">Cancel case</button>
        @endif
    @endcan
@endsection

@section('content')
@if($case->status === 'cancelled')
    <div class="alert alert-secondary">Cancelled on {{ $case->cancelled_at?->format('d M Y H:i') }}: {{ $case->cancellation_reason }}. All invoices were cancelled and deductions refunded.</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'Rate per member', 'value' => money($case->contribution_amount)])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'Members invoiced', 'value' => $summary['invoices'], 'hint' => $summary['paid_count'].' fully paid'])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'Collected', 'value' => money($summary['collected']), 'hint' => 'of '.money($summary['expected'])])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'Outstanding', 'value' => money($summary['outstanding']), 'class' => $summary['outstanding'] > 0 ? 'text-danger' : 'text-success'])</div>
</div>

@if($summary['expected'] > 0)
    @php($pct = min(100, round($summary['collected'] / $summary['expected'] * 100)))
    <div class="progress mb-3" style="height: 10px;" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar bg-brand" style="width: {{ $pct }}%"></div>
    </div>
@endif

<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Where the money goes</span>
        <span class="small text-muted">Each member's {{ money($case->contribution_amount) }}: office {{ money($perMember['office']) }}, transport {{ money($perMember['transport']) }}, welfare {{ money($perMember['welfare']) }}. Office and transport are taken first.</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Fund</th><th class="text-end">Expected</th><th class="text-end">Collected</th><th class="text-end">Disbursed</th><th class="text-end">Available</th></tr></thead>
                <tbody>
                    <tr>
                        <td><strong>Welfare</strong><div class="small text-muted">For the bereaved family</div></td>
                        <td class="text-end amount">{{ number_format($funds['welfare_due'], 2) }}</td>
                        <td class="text-end amount fw-semibold text-brand">{{ number_format($funds['welfare'], 2) }}</td>
                        <td class="text-end amount">{{ number_format($funds['welfare_disbursed'], 2) }}</td>
                        <td class="text-end amount fw-semibold">{{ number_format($funds['welfare_available'], 2) }}</td>
                    </tr>
                    <tr>
                        <td><strong>Transport</strong></td>
                        <td class="text-end amount">{{ number_format($funds['transport_due'], 2) }}</td>
                        <td class="text-end amount fw-semibold">{{ number_format($funds['transport'], 2) }}</td>
                        <td class="text-end amount">{{ number_format($funds['transport_disbursed'], 2) }}</td>
                        <td class="text-end amount fw-semibold">{{ number_format($funds['transport_available'], 2) }}</td>
                    </tr>
                    <tr>
                        <td><strong>Office expenditure</strong><div class="small text-muted">Goes to the office account @can('office.view')(<a href="{{ route('office.index') }}">view</a>)@endcan</div></td>
                        <td class="text-end amount">{{ number_format($funds['office_due'], 2) }}</td>
                        <td class="text-end amount fw-semibold">{{ number_format($funds['office'], 2) }}</td>
                        <td class="text-end text-muted">-</td>
                        <td class="text-end text-muted">-</td>
                    </tr>
                </tbody>
                <tfoot><tr class="fw-semibold"><td>Total</td>
                    <td class="text-end amount">{{ number_format($summary['expected'], 2) }}</td>
                    <td class="text-end amount">{{ number_format($summary['collected'], 2) }}</td>
                    <td class="text-end amount">{{ number_format($funds['welfare_disbursed'] + $funds['transport_disbursed'], 2) }}</td>
                    <td></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Case details</div>
    <div class="card-body">
        <dl class="dl-grid">
            <dt>Reported by member</dt><dd><a href="{{ route('members.show', $case->member_id) }}">{{ $case->member->full_name }}</a> ({{ $case->member->member_no }})</dd>
            <dt>Deceased</dt><dd>{{ $case->deceased_name }} ({{ $case->category_label }})</dd>
            <dt>Date of bereavement</dt><dd>{{ $case->bereavement_date->format('d M Y') }}</dd>
            <dt>Remarks</dt><dd>{{ $case->remarks ?: '-' }}</dd>
            <dt>Created</dt><dd>{{ $case->created_at->format('d M Y H:i') }} by {{ $case->creator?->name }}</dd>
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-header">Member contributions</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead><tr><th>Member</th><th>Invoice</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Outstanding</th><th>Status</th>@if($canPay || $canRemind)<th class="text-end">Actions</th>@endif</tr></thead>
                <tbody>
                @forelse($invoices as $i)
                    <tr>
                        <td>{{ $i->member->full_name }}<div class="small text-muted">{{ $i->member->member_no }}</div></td>
                        <td><a href="{{ route('invoices.show', $i) }}">{{ $i->invoice_no }}</a></td>
                        <td class="text-end amount">{{ number_format($i->amount, 2) }}</td>
                        <td class="text-end amount">{{ number_format($i->amount_paid, 2) }}</td>
                        <td class="text-end amount {{ $i->outstanding > 0 ? 'text-danger' : '' }}">{{ number_format($i->outstanding, 2) }}</td>
                        <td><span class="badge {{ status_badge($i->status) }}">{{ $i->status_label }}</span></td>
                        @if($canPay || $canRemind)
                            <td class="text-end text-nowrap">
                                @if($unpaid->contains('id', $i->id))
                                    @if($canPay)
                                        <button type="button" class="btn btn-sm btn-outline-primary js-pay" data-member="{{ $i->member_id }}">Pay</button>
                                    @endif
                                    @if($canRemind)
                                        <form method="POST" action="{{ route('cases.remind', $case) }}" class="d-inline" data-confirm="Send an SMS reminder to {{ $i->member->full_name }} for {{ money($i->outstanding) }}?">
                                            @csrf @include('partials.submission')
                                            <input type="hidden" name="invoice_ids[]" value="{{ $i->id }}">
                                            <button class="btn btn-sm btn-outline-warning">Remind</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ ($canPay || $canRemind) ? 7 : 6 }}" class="empty-state">No invoices for this case.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canPay)
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('cases.payments.store', $case) }}">
            @csrf @include('partials.submission')
            <div class="modal-header"><h5 class="modal-title">Record payment for {{ $case->case_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">The amount is credited to the member's wallet and used first for their contribution on this case. It can be the full amount or part of it; anything extra stays in the wallet.</p>
                <div class="mb-3">
                    <label class="form-label" for="payMember">Member</label>
                    <select name="member_id" id="payMember" class="form-select" required>
                        <option value="">Select member...</option>
                        @foreach($invoices->where('status', '!=', 'cancelled') as $i)
                            <option value="{{ $i->member_id }}" data-outstanding="{{ number_format($i->outstanding, 2, '.', '') }}" @selected(old('member_id') == $i->member_id)>
                                {{ $i->member->full_name }} ({{ $i->member->member_no }}) - owes {{ number_format($i->outstanding, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="payAmount">Amount ({{ $s['currency'] }})</label>
                        <input type="number" step="0.01" min="1" name="amount" id="payAmount" class="form-control" value="{{ old('amount') }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="payReference">Transaction code</label>
                        <input type="text" name="reference" id="payReference" class="form-control text-uppercase" maxlength="60" value="{{ old('reference') }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="payDate">Payment date</label>
                        <input type="date" name="payment_date" id="payDate" class="form-control" value="{{ old('payment_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="payCost">Transaction cost <span class="text-muted small">(optional)</span></label>
                        <input type="number" step="0.01" min="0" name="transaction_cost" id="payCost" class="form-control" value="{{ old('transaction_cost') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="payRemarks">Remarks</label>
                        <input type="text" name="remarks" id="payRemarks" class="form-control" maxlength="250" value="{{ old('remarks') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Save payment</button></div>
        </form>
    </div></div>
</div>
@endif

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Disbursements</span>
        @if($canDisburse)
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#disburseModal">Record disbursement</button>
        @endif
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Date</th><th>Ref</th><th>Fund</th><th>Recipient</th><th class="text-end">Amount</th><th>Remarks</th><th>Status</th>@if($canDisburse)<th></th>@endif</tr></thead>
                <tbody>
                @forelse($disbursements as $d)
                    <tr class="{{ $d->status === 'voided' ? 'text-muted' : '' }}">
                        <td>{{ $d->disbursement_date->format('d M Y') }}</td>
                        <td>{{ $d->reference }}@if($d->payment_ref)<div class="small text-muted">{{ $d->payment_ref }}</div>@endif</td>
                        <td>{{ $d->fund_label }}</td>
                        <td>{{ $d->recipient }}@if($d->recipient_phone)<div class="small text-muted">{{ $d->recipient_phone }}</div>@endif</td>
                        <td class="text-end amount">{{ number_format($d->amount, 2) }}</td>
                        <td class="small">{{ $d->remarks ?: '-' }}<div class="text-muted">by {{ $d->recorder?->name }}</div></td>
                        <td>
                            @if($d->status === 'voided')
                                <span class="badge text-bg-secondary">Void</span>
                                <div class="small">{{ $d->void_reason }}</div>
                            @else
                                <span class="badge text-bg-success">Paid out</span>
                            @endif
                        </td>
                        @if($canDisburse)
                            <td class="text-end">
                                @if($d->status === 'active')
                                    <form method="POST" action="{{ route('disbursements.void', $d) }}" class="d-flex gap-1 justify-content-end" data-confirm="Void disbursement {{ $d->reference }}? The money becomes available on the case again.">
                                        @csrf @include('partials.submission')
                                        <input type="text" name="reason" class="form-control form-control-sm" style="max-width: 140px;" placeholder="Reason" required maxlength="250">
                                        <button class="btn btn-sm btn-outline-danger">Void</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canDisburse ? 8 : 7 }}" class="empty-state">No money has been disbursed for this case yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canDisburse)
<div class="modal fade" id="disburseModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('cases.disburse', $case) }}">
            @csrf @include('partials.submission')
            <div class="modal-header"><h5 class="modal-title">Disburse money for {{ $case->case_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="dFund">Pay from</label>
                    <select name="fund" id="dFund" class="form-select" required>
                        <option value="welfare" @selected(old('fund', 'welfare') === 'welfare')>Welfare fund ({{ money($funds['welfare_available']) }} available)</option>
                        <option value="transport" @selected(old('fund') === 'transport')>Transport ({{ money($funds['transport_available']) }} available)</option>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="dRecipient">Recipient</label>
                        <input type="text" name="recipient" id="dRecipient" class="form-control" maxlength="150" required value="{{ old('recipient', $case->category === 'self' ? '' : $case->member->full_name) }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="dPhone">Recipient phone <span class="text-muted small">(optional)</span></label>
                        <input type="text" name="recipient_phone" id="dPhone" class="form-control" maxlength="30" value="{{ old('recipient_phone') }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="dAmount">Amount received ({{ $s['currency'] }})</label>
                        <input type="number" step="0.01" min="1" name="amount" id="dAmount" class="form-control" required value="{{ old('amount') }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="dDate">Date</label>
                        <input type="date" name="disbursement_date" id="dDate" class="form-control" required max="{{ date('Y-m-d') }}" value="{{ old('disbursement_date', date('Y-m-d')) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="dRef">Payment reference <span class="text-muted small">(optional, e.g. M-Pesa code or cheque no.)</span></label>
                        <input type="text" name="payment_ref" id="dRef" class="form-control text-uppercase" maxlength="60" value="{{ old('payment_ref') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="dRemarks">Remarks <span class="text-muted small">(optional)</span></label>
                        <textarea name="remarks" id="dRemarks" class="form-control" rows="2" maxlength="250">{{ old('remarks') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Save disbursement</button></div>
        </form>
    </div></div>
</div>
@endif

<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('cases.cancel', $case) }}" data-confirm="Cancel this case? This cannot be undone.">
            @csrf @include('partials.submission')
            <div class="modal-header"><h5 class="modal-title">Cancel case {{ $case->case_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small">All invoices for this case will be cancelled and any amount already deducted will be refunded to each member's wallet.</p>
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="3" required maxlength="250"></textarea>
            </div>
            <div class="modal-footer"><button class="btn btn-danger">Cancel case</button></div>
        </form>
    </div></div>
</div>
@endsection

@if($canPay)
@push('scripts')
<script>
(function () {
    var modal = document.getElementById('payModal');
    // Pre-fill the outstanding amount; lower it for a part payment.
    $('#payMember').on('change', function () {
        var o = $(this).find(':selected').data('outstanding');
        if (o !== undefined && parseFloat(o) > 0) $('#payAmount').val(o);
    });
    $(document).on('click', '.js-pay', function () {
        $('#payMember').val(String($(this).data('member'))).trigger('change');
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });
    @if($errors->any() && old('reference'))
        bootstrap.Modal.getOrCreateInstance(modal).show();
    @endif
})();
</script>
@endpush
@endif

@if($canDisburse && $errors->any() && old('recipient'))
@push('scripts')
<script>bootstrap.Modal.getOrCreateInstance(document.getElementById('disburseModal')).show();</script>
@endpush
@endif
