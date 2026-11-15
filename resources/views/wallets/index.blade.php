@extends('layouts.app')
@section('title', 'Member wallets')
@section('subtitle', 'Total held for members: '.money($members->sum(fn ($m) => $m->balance)))

@section('actions')
    @can('deposits.record')
        <form method="POST" action="{{ route('wallets.remind') }}" data-confirm="Send a low-balance reminder to every active member below {{ money($s['low_balance_threshold']) }}?">
            @csrf @include('partials.submission')
            <button class="btn btn-outline-primary">Send low-balance reminders</button>
        </form>
        <a href="{{ route('payments.create') }}" class="btn btn-primary">Record deposit</a>
    @endcan
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead><tr><th>Member No</th><th>Member</th><th>Phone</th><th>Status</th><th class="text-end">Balance</th><th class="text-end">Outstanding</th></tr></thead>
                <tbody>
                @forelse($members as $m)
                    <tr>
                        <td>{{ $m->member_no }}</td>
                        <td><a href="{{ route('wallets.show', $m) }}">{{ $m->full_name }}</a></td>
                        <td>{{ $m->phone }}</td>
                        <td><span class="badge {{ status_badge($m->status) }}">{{ $m->status_label }}</span></td>
                        <td class="text-end amount {{ $m->balance < 0 ? 'text-danger' : '' }}" data-order="{{ $m->balance }}">{{ number_format($m->balance, 2) }}</td>
                        <td class="text-end amount {{ $m->outstanding > 0 ? 'text-danger' : '' }}" data-order="{{ (float) $m->outstanding }}">{{ number_format((float) $m->outstanding, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No approved members yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
