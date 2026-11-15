@extends('layouts.app')
@section('title', 'Audit logs')
@section('subtitle', 'A read-only record of important actions. Entries cannot be edited or deleted.')

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2" method="GET">
            <div class="col-md-3">
                <select name="action" class="form-select">
                    <option value="">All areas</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" @selected(request('action') === $a)>{{ ucfirst($a) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><input type="text" name="user" value="{{ request('user') }}" class="form-control" placeholder="User name"></div>
            <div class="col-6 col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
            <div class="col-6 col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Record</th><th>Description</th><th>IP</th><th></th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="text-nowrap small">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                    <td class="small">{{ $log->user_name }}</td>
                    <td><code class="small">{{ $log->action }}</code></td>
                    <td class="small">{{ $log->subject_label }}</td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($log->description, 70) }}</td>
                    <td class="small">{{ $log->ip_address }}</td>
                    <td><a href="{{ route('admin.audit.show', $log) }}" class="btn btn-sm btn-light">View</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state">No audit entries.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="card-footer bg-white">{{ $logs->links() }}</div>@endif
</div>
@endsection
