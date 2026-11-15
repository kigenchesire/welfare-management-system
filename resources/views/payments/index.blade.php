@extends('layouts.app')
@section('title', 'Deposits')

@section('actions')
    @can('deposits.record')<a href="{{ route('payments.create') }}" class="btn btn-primary">Record deposit</a>@endcan
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-6 col-md-3"><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
            <div class="col-6 col-md-3"><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
            <div class="col-md-4 text-md-end align-self-center">Total: <strong class="amount">{{ money($payments->where('status', 'confirmed')->sum('amount')) }}</strong></div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead><tr><th>Date</th><th>Receipt</th><th>Reference</th><th>Member</th><th class="text-end">Amount</th><th class="text-end">Txn cost</th><th>Status</th><th>Recorded by</th></tr></thead>
                <tbody>
                @forelse($payments as $p)
                    <tr>
                        <td data-order="{{ $p->payment_date->format('Y-m-d') }}">{{ $p->payment_date->format('d M Y') }}</td>
                        <td><a href="{{ route('payments.show', $p) }}">{{ $p->receipt_no }}</a></td>
                        <td>{{ $p->reference }}</td>
                        <td>{{ $p->member->full_name }}</td>
                        <td class="text-end amount">{{ number_format($p->amount, 2) }}</td>
                        <td class="text-end amount">{{ $p->transaction_cost !== null ? number_format($p->transaction_cost, 2) : '-' }}</td>
                        <td><span class="badge {{ status_badge($p->status) }}">{{ ucfirst($p->status) }}</span></td>
                        <td class="small">{{ $p->recorder?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">No deposits in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
