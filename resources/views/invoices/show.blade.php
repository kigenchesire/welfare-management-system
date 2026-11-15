@extends('layouts.app')
@section('title', 'Invoice '.$invoice->invoice_no)
@section('subtitle')
    <span class="badge {{ status_badge($invoice->status) }}">{{ $invoice->status_label }}</span>
@endsection

@section('actions')
    <button onclick="window.print()" class="btn btn-outline-secondary">Print</button>
    @if(!$portal)
        @can('invoices.manage')
            @if(in_array($invoice->status, ['unpaid', 'partially_paid']))
                <form method="POST" action="{{ route('invoices.pay', $invoice) }}" data-confirm="Pay this invoice from the member's wallet balance ({{ money($invoice->member->balance) }})?">
                    @csrf @include('partials.submission')
                    <button class="btn btn-outline-primary">Pay from wallet</button>
                </form>
            @endif
            @if($invoice->status !== 'cancelled')
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelInvoice">Cancel invoice</button>
            @endif
        @endcan
    @endif
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <div class="fw-bold fs-5">{{ $s['group_name'] }}</div>
                <div class="small text-muted">{{ $s['address'] }} &middot; {{ $s['phone'] }}</div>
            </div>
            <div class="text-md-end">
                <div class="fw-bold">INVOICE {{ $invoice->invoice_no }}</div>
                <div class="small">Date: {{ $invoice->invoice_date->format('d M Y') }}</div>
                @if($invoice->due_date)<div class="small">Due: {{ $invoice->due_date->format('d M Y') }}</div>@endif
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6">
                <dl class="dl-grid">
                    <dt>Member</dt><dd>{{ $invoice->member->full_name }}</dd>
                    <dt>Member No</dt><dd>{{ $invoice->member->member_no }}</dd>
                    <dt>National ID</dt><dd>{{ $invoice->member->national_id }}</dd>
                    @if($invoice->welfareCase)
                        <dt>Case reference</dt><dd>{{ $invoice->welfareCase->case_no }}</dd>
                    @endif
                    <dt>Category</dt><dd>{{ $invoice->category_label }}</dd>
                </dl>
            </div>
            <div class="col-md-6">
                <dl class="dl-grid">
                    <dt>Description</dt><dd>{{ $invoice->title }}@if($invoice->description)<div class="small text-muted">{{ $invoice->description }}</div>@endif</dd>
                    <dt>Amount</dt><dd class="amount">{{ money($invoice->amount) }}</dd>
                    <dt>Amount paid</dt><dd class="amount">{{ money($invoice->amount_paid) }}</dd>
                    <dt>Outstanding</dt><dd class="amount fw-bold {{ $invoice->outstanding > 0 ? 'text-danger' : '' }}">{{ money($invoice->outstanding) }}</dd>
                    <dt>Status</dt><dd>{{ $invoice->status_label }}</dd>
                    @if($invoice->remarks)<dt>Remarks</dt><dd>{{ $invoice->remarks }}</dd>@endif
                    @if($invoice->cancellation_reason)<dt>Cancelled</dt><dd>{{ $invoice->cancellation_reason }}</dd>@endif
                </dl>
            </div>
        </div>
        @if($invoice->outstanding > 0)
            <div class="alert alert-light border mt-3 mb-0 small">
                Pay via <strong>{{ $s['payment_method'] }} {{ $s['paybill_number'] }}</strong> &middot; {{ $s['account_instructions'] }}.
            </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">Payments applied</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Date</th><th>Reference</th><th>Type</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            @forelse($invoice->transactions as $t)
                <tr>
                    <td>{{ $t->transaction_date->format('d M Y H:i') }}</td>
                    <td>{{ $t->reference }}</td>
                    <td>{{ $t->type_label }}</td>
                    <td class="text-end amount {{ $t->direction }}">{{ $t->direction === 'credit' ? '+' : '-' }}{{ number_format($t->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty-state">No payments applied yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if(!$portal)
<div class="modal fade" id="cancelInvoice" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" data-confirm="Cancel this invoice?">
            @csrf @include('partials.submission')
            <div class="modal-header"><h5 class="modal-title">Cancel invoice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small">Any amount already paid ({{ money($invoice->amount_paid) }}) will be refunded to the member's wallet.</p>
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="3" required maxlength="250"></textarea>
            </div>
            <div class="modal-footer"><button class="btn btn-danger">Cancel invoice</button></div>
        </form>
    </div></div>
</div>
@endif
@endsection
