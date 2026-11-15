@extends('layouts.app')
@section('title', 'Wallet transactions')

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2" method="GET">
            <div class="col-6 col-md-3"><label class="form-label small">From</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
            <div class="col-6 col-md-3"><label class="form-label small">To</label><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
            <div class="col-md-3">
                <label class="form-label small">Type</label>
                <select name="type" class="form-select">
                    <option value="">All types</option>
                    @foreach(\App\Models\WalletTransaction::TYPES as $k => $label)
                        <option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-primary">Filter</button>
                @can('reports.financial')
                    <a href="{{ route('reports.show', ['wallet-transactions', 'from' => $from, 'to' => $to, 'type' => request('type')]) }}" class="btn btn-outline-secondary">Export</a>
                @endcan
            </div>
        </form>
    </div>
    @include('wallets._ledger', ['transactions' => $transactions, 'showMember' => true])
</div>
@endsection
