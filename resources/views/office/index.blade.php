@extends('layouts.app')
@section('title', $account->name)
@section('subtitle', 'Account '.$account->account_no.' · office expenditure money')

@php($canManage = auth()->user()->can('office.manage'))

@section('actions')
    @if($canManage)
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="bi bi-dash-circle me-1"></i>Add expense</button>
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#topupModal"><i class="bi bi-plus-circle me-1"></i>Top up</button>
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#accountModal" title="Account name and number"><i class="bi bi-pencil"></i></button>
    @endif
    @can('reports.financial')
        <a href="{{ route('reports.show', ['office-statement', 'from' => $from, 'to' => $to]) }}" class="btn btn-outline-secondary">Statement report</a>
    @endcan
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-3">@include('partials.stat', ['label' => 'Account balance', 'value' => money($totals['balance']), 'class' => $totals['balance'] < 0 ? 'text-danger' : 'text-brand', 'hint' => 'Account '.$account->account_no])</div>
    <div class="col-6 col-md-3">@include('partials.stat', ['label' => 'From welfare cases', 'value' => money($totals['case_income']), 'hint' => 'Office share of member payments'])</div>
    <div class="col-6 col-md-3">@include('partials.stat', ['label' => 'Top-ups', 'value' => money($totals['topups']), 'hint' => 'From other sources'])</div>
    <div class="col-md-3">@include('partials.stat', ['label' => 'Expenses', 'value' => money($totals['expenses']), 'hint' => ($totals['reversals_in'] + $totals['reversals_out']) > 0 ? 'Reversals: +'.number_format($totals['reversals_in'], 2).' / -'.number_format($totals['reversals_out'], 2) : null])</div>
</div>
@if($totals['balance'] < 0)
    <div class="alert alert-warning">The balance is below zero because a welfare case was cancelled after its office money had been spent. Top up the account to cover it.</div>
@endif

<div class="card">
    <div class="card-header">Statement</div>
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-6 col-md-3"><input type="date" name="from" value="{{ $from }}" class="form-control" aria-label="From"></div>
            <div class="col-6 col-md-3"><input type="date" name="to" value="{{ $to }}" class="form-control" aria-label="To"></div>
            <div class="col-md-3">
                <select name="type" class="form-select" aria-label="Type">
                    <option value="">All entries</option>
                    @foreach(['case_income' => 'Case income', 'topup' => 'Top-ups', 'expense' => 'Expenses', 'reversal' => 'Reversals'] as $k => $label)
                        <option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary">Apply</button></div>
            @if($from || $to || request('type'))<div class="col-auto"><a href="{{ route('office.index') }}" class="btn btn-light">Clear</a></div>@endif
        </form>

        @if($from)
            <p class="small text-muted mb-2">Opening balance on {{ $from }}: <strong>{{ money($statement['opening']) }}</strong></p>
        @endif
        <div class="table-responsive">
            <table class="table table-hover datatable" data-order='[]'>
                <thead><tr><th>Date</th><th>Ref</th><th>Type</th><th>Details</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th>@if($canManage)<th></th>@endif</tr></thead>
                <tbody>
                @foreach(array_reverse($statement['rows']) as $r)
                    <tr>
                        <td data-order="{{ $r['date'] }}">{{ \Illuminate\Support\Carbon::parse($r['date'])->format('d M Y') }}</td>
                        <td>
                            @if(isset($r['case_id']))
                                <a href="{{ route('cases.show', $r['case_id']) }}">{{ $r['reference'] }}</a>
                            @else
                                {{ $r['reference'] }}
                            @endif
                        </td>
                        <td>{{ $r['type_label'] }}@if($r['category'])<div class="small text-muted">{{ $r['category'] }}</div>@endif</td>
                        <td class="small">{{ $r['details'] }}@if($r['by'])<div class="text-muted">by {{ $r['by'] }}</div>@endif</td>
                        <td class="text-end amount text-success">{{ $r['in'] ? number_format($r['in'], 2) : '' }}</td>
                        <td class="text-end amount text-danger">{{ $r['out'] ? number_format($r['out'], 2) : '' }}</td>
                        <td class="text-end amount fw-semibold">{{ number_format($r['balance'], 2) }}</td>
                        @if($canManage)
                            <td class="text-end">
                                @if($r['model'] && $r['type'] !== 'reversal' && ! isset($reversedIds[$r['model']->id]))
                                    <form method="POST" action="{{ route('office.reverse', $r['model']) }}" class="d-flex gap-1 justify-content-end" data-confirm="Reverse {{ $r['reference'] }}? An opposite entry will be posted.">
                                        @csrf @include('partials.submission')
                                        <input type="text" name="reason" class="form-control form-control-sm" style="max-width: 130px;" placeholder="Reason" required maxlength="200">
                                        <button class="btn btn-sm btn-outline-danger">Reverse</button>
                                    </form>
                                @elseif($r['model'] && isset($reversedIds[$r['model']->id]))
                                    <span class="badge text-bg-secondary">Reversed</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canManage)
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('office.expense') }}">
            @csrf @include('partials.submission')
            <input type="hidden" name="_form" value="expense">
            <div class="modal-header"><h5 class="modal-title">Add office expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">The amount is deducted from the office account straight away. Available: <strong>{{ money($totals['balance']) }}</strong>.</p>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="eDesc">What was it for?</label>
                        <input type="text" name="description" id="eDesc" class="form-control" required maxlength="250" value="{{ old('_form') === 'expense' ? old('description') : '' }}" placeholder="e.g. Printing of member cards">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="eAmount">Amount ({{ $s['currency'] }})</label>
                        <input type="number" step="0.01" min="1" name="amount" id="eAmount" class="form-control" required value="{{ old('_form') === 'expense' ? old('amount') : '' }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="eDate">Date</label>
                        <input type="date" name="transaction_date" id="eDate" class="form-control" required max="{{ date('Y-m-d') }}" value="{{ old('_form') === 'expense' ? old('transaction_date') : date('Y-m-d') }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="eCat">Category <span class="text-muted small">(optional)</span></label>
                        <input type="text" name="category" id="eCat" class="form-control" maxlength="60" list="expenseCategories" value="{{ old('_form') === 'expense' ? old('category') : '' }}">
                        <datalist id="expenseCategories">
                            <option value="Stationery"><option value="Airtime & SMS"><option value="Transport"><option value="Meetings"><option value="Bank charges"><option value="Rent"><option value="Other">
                        </datalist>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="eParty">Paid to <span class="text-muted small">(optional)</span></label>
                        <input type="text" name="party" id="eParty" class="form-control" maxlength="150" value="{{ old('_form') === 'expense' ? old('party') : '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="eRef">Receipt / reference <span class="text-muted small">(optional)</span></label>
                        <input type="text" name="external_ref" id="eRef" class="form-control" maxlength="60" value="{{ old('_form') === 'expense' ? old('external_ref') : '' }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Deduct expense</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="topupModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('office.topup') }}">
            @csrf @include('partials.submission')
            <input type="hidden" name="_form" value="topup">
            <div class="modal-header"><h5 class="modal-title">Top up office account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="tParty">Source of the money</label>
                        <input type="text" name="party" id="tParty" class="form-control" required maxlength="150" value="{{ old('_form') === 'topup' ? old('party') : '' }}" placeholder="e.g. Fundraising, donation, registration fees">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="tAmount">Amount ({{ $s['currency'] }})</label>
                        <input type="number" step="0.01" min="1" name="amount" id="tAmount" class="form-control" required value="{{ old('_form') === 'topup' ? old('amount') : '' }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="tDate">Date</label>
                        <input type="date" name="transaction_date" id="tDate" class="form-control" required max="{{ date('Y-m-d') }}" value="{{ old('_form') === 'topup' ? old('transaction_date') : date('Y-m-d') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="tRef">Reference <span class="text-muted small">(optional)</span></label>
                        <input type="text" name="external_ref" id="tRef" class="form-control" maxlength="60" value="{{ old('_form') === 'topup' ? old('external_ref') : '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="tDesc">Remarks <span class="text-muted small">(optional)</span></label>
                        <input type="text" name="description" id="tDesc" class="form-control" maxlength="250" value="{{ old('_form') === 'topup' ? old('description') : '' }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Add top-up</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('office.account') }}">
            @csrf @method('PUT') @include('partials.submission')
            <div class="modal-header"><h5 class="modal-title">Office account details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="aName">Account name</label>
                    <input type="text" name="name" id="aName" class="form-control" required maxlength="120" value="{{ $account->name }}">
                </div>
                <label class="form-label" for="aNo">Account ID / number</label>
                <input type="text" name="account_no" id="aNo" class="form-control" required maxlength="40" value="{{ $account->account_no }}">
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>
@endif
@endsection

@if($canManage && $errors->any() && in_array(old('_form'), ['expense', 'topup'], true))
@push('scripts')
<script>bootstrap.Modal.getOrCreateInstance(document.getElementById(@json(old('_form').'Modal'))).show();</script>
@endpush
@endif
