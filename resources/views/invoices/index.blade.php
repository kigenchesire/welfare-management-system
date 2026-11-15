@extends('layouts.app')
@section('title', $type === 'custom' ? 'Custom invoices' : ($type === 'welfare' ? 'Welfare invoices' : 'All invoices'))

@section('actions')
    @can('invoices.manage')<a href="{{ route('invoices.create') }}" class="btn btn-primary">New custom invoice</a>@endcan
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-auto">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="">All types</option>
                    <option value="welfare" @selected($type === 'welfare')>Welfare contributions</option>
                    <option value="custom" @selected($type === 'custom')>Custom invoices</option>
                </select>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option value="open" @selected(request('status') === 'open')>Outstanding (unpaid + partial)</option>
                    @foreach(\App\Models\Invoice::STATUSES as $k => $label)
                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead><tr><th>Invoice</th><th>Date</th><th>Member</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Outstanding</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($invoices as $i)
                    <tr>
                        <td><a href="{{ route('invoices.show', $i) }}">{{ $i->invoice_no }}</a></td>
                        <td data-order="{{ $i->invoice_date->format('Y-m-d') }}">{{ $i->invoice_date->format('d M Y') }}</td>
                        <td>{{ $i->member->full_name }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($i->title, 60) }}</td>
                        <td class="text-end amount">{{ number_format($i->amount, 2) }}</td>
                        <td class="text-end amount">{{ number_format($i->amount_paid, 2) }}</td>
                        <td class="text-end amount {{ $i->outstanding > 0 ? 'text-danger' : '' }}">{{ number_format($i->outstanding, 2) }}</td>
                        <td><span class="badge {{ status_badge($i->status) }}">{{ $i->status_label }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">No invoices found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
