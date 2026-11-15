@extends('layouts.app')
@section('title', 'My invoices')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable" data-order='[]'>
                <thead><tr><th>Invoice</th><th>Date</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Outstanding</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($invoices as $i)
                    <tr>
                        <td><a href="{{ route('member.invoice', $i->id) }}">{{ $i->invoice_no }}</a></td>
                        <td data-order="{{ $i->invoice_date->format('Y-m-d') }}">{{ $i->invoice_date->format('d M Y') }}</td>
                        <td>{{ $i->title }}</td>
                        <td class="text-end amount">{{ number_format($i->amount, 2) }}</td>
                        <td class="text-end amount">{{ number_format($i->amount_paid, 2) }}</td>
                        <td class="text-end amount {{ $i->outstanding > 0 ? 'text-danger' : '' }}">{{ number_format($i->outstanding, 2) }}</td>
                        <td><span class="badge {{ status_badge($i->status) }}">{{ $i->status_label }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">No invoices yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
