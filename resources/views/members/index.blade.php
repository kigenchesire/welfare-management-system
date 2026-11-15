@extends('layouts.app')
@section('title', 'Members')

@section('actions')
    @can('members.manage')<a href="{{ route('members.create') }}" class="btn btn-primary">Register member</a>@endcan
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-auto">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All statuses (except rejected)</option>
                    @foreach(\App\Models\Member::STATUSES as $k => $label)
                        <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead><tr><th>Member No</th><th>Name</th><th>National ID</th><th>Phone</th><th>Registered</th><th>Status</th><th class="text-end">Wallet</th></tr></thead>
                <tbody>
                @forelse($members as $m)
                    <tr>
                        <td>{{ $m->member_no ?? '-' }}</td>
                        <td><a href="{{ route('members.show', $m) }}">{{ $m->full_name }}</a></td>
                        <td>{{ $m->national_id }}</td>
                        <td>{{ $m->phone }}</td>
                        <td data-order="{{ $m->registration_date->format('Y-m-d') }}">{{ $m->registration_date->format('d M Y') }}</td>
                        <td><span class="badge {{ status_badge($m->status) }}">{{ $m->status_label }}</span></td>
                        <td class="text-end amount" data-order="{{ $m->balance }}">{{ number_format($m->balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">No members found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
