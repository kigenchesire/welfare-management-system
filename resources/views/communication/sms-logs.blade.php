@extends('layouts.app')
@section('title', 'SMS logs')

@section('content')
@unless(\App\Support\Settings::bool('sms_enabled'))
    <div class="alert alert-warning">SMS sending is currently disabled. Messages are logged with status "disabled". Configure the gateway in System Settings &rsaquo; SMS Gateway.</div>
@endunless
<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2" method="GET">
            <div class="col-auto">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach(['sent', 'failed', 'pending', 'disabled'] as $st)
                        <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select name="category" class="form-select">
                    <option value="">All types</option>
                    @foreach(['case', 'invoice', 'payment', 'low_balance', 'announcement', 'credentials', 'registration', 'test'] as $c)
                        <option value="{{ $c }}" @selected(request('category') === $c)>{{ ucfirst(str_replace('_', ' ', $c)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Date</th><th>Phone</th><th>Member</th><th>Type</th><th>Message</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($logs as $l)
                <tr>
                    <td class="text-nowrap small">{{ $l->created_at->format('d M Y H:i') }}</td>
                    <td class="small">{{ $l->phone }}</td>
                    <td class="small">{{ $l->member?->full_name }}</td>
                    <td class="small">{{ ucfirst(str_replace('_', ' ', $l->category)) }}</td>
                    <td class="small" style="max-width: 380px;">{{ $l->message }}</td>
                    <td><span class="badge {{ status_badge($l->status) }}" title="{{ $l->response }}">{{ ucfirst($l->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">No SMS messages logged.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="card-footer bg-white">{{ $logs->links() }}</div>@endif
</div>
@endsection
