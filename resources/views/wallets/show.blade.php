@extends('layouts.app')
@section('title', 'Wallet: '.$member->full_name)
@section('subtitle', $member->member_no.' · ID '.$member->national_id)

@section('actions')
    <a href="{{ route('members.show', $member) }}" class="btn btn-outline-secondary">Member profile</a>
    @can('deposits.record')<a href="{{ route('payments.create', ['member_id' => $member->id]) }}" class="btn btn-primary">Record deposit</a>@endcan
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4">@include('partials.stat', ['label' => 'Wallet balance', 'value' => money($member->balance), 'class' => $member->balance < 0 ? 'text-danger' : 'text-brand'])</div>
    <div class="col-md-4">@include('partials.stat', ['label' => 'Outstanding invoices', 'value' => money($outstanding), 'class' => $outstanding > 0 ? 'text-danger' : ''])</div>
    <div class="col-md-4">@include('partials.stat', ['label' => 'Net position', 'value' => money($member->balance - $outstanding)])</div>
</div>

@can('adjustments.manage')
<div class="card mb-3">
    <div class="card-header">
        <a class="text-reset text-decoration-none" data-bs-toggle="collapse" href="#adjustForm">Post an adjustment <i class="bi bi-chevron-down small"></i></a>
    </div>
    <div class="collapse" id="adjustForm">
        <div class="card-body">
            <p class="small text-muted">Adjustments correct mistakes without deleting history. They appear in the ledger and audit log.</p>
            <form method="POST" action="{{ route('wallets.adjust', $member) }}" class="row g-2" data-confirm="Post this adjustment to the member's wallet?">
                @csrf @include('partials.submission')
                <div class="col-md-2">
                    <select name="direction" class="form-select">
                        <option value="credit">Credit (+)</option>
                        <option value="debit">Debit (-)</option>
                    </select>
                </div>
                <div class="col-md-2"><input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Amount" required></div>
                <div class="col-md-5"><input type="text" name="reason" class="form-control" placeholder="Reason" required maxlength="250"></div>
                <div class="col-md-3"><button class="btn btn-primary w-100">Post adjustment</button></div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="authorize_negative" value="1" id="authNeg">
                        <label class="form-check-label small" for="authNeg">Authorise a negative balance if the debit exceeds the available balance</label>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<div class="card">
    <div class="card-header d-flex justify-content-between">Ledger <button onclick="window.print()" class="btn btn-sm btn-light no-print">Print</button></div>
    @include('wallets._ledger', ['transactions' => $transactions, 'showMember' => false])
</div>
@endsection
