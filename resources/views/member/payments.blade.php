@extends('layouts.app')
@section('title', 'My payments')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable">
                <thead><tr><th>Date</th><th>Receipt</th><th>Reference</th><th class="text-end">Amount</th><th>Status</th><th class="no-sort"></th></tr></thead>
                <tbody>
                @forelse($payments as $p)
                    <tr>
                        <td data-order="{{ $p->payment_date->format('Y-m-d') }}">{{ $p->payment_date->format('d M Y') }}</td>
                        <td>{{ $p->receipt_no }}</td>
                        <td>{{ $p->reference }}</td>
                        <td class="text-end amount">{{ number_format($p->amount, 2) }}</td>
                        <td><span class="badge {{ status_badge($p->status) }}">{{ ucfirst($p->status) }}</span></td>
                        <td class="text-end"><a href="{{ route('member.receipt', $p->id) }}" class="btn btn-sm btn-outline-primary">Receipt</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No payments recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
