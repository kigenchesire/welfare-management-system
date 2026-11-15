@extends('layouts.app')
@section('title', 'Welfare cases')

@section('actions')
    @can('cases.manage')<a href="{{ route('cases.create') }}" class="btn btn-primary">New welfare case</a>@endcan
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-auto">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach(\App\Models\WelfareCase::STATUSES as $k => $label)
                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead><tr><th>Case</th><th>Date</th><th>Deceased</th><th>Member</th><th class="text-end">Rate</th><th class="text-end">Collected</th><th class="text-end">Outstanding</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($cases as $c)
                    @php($sum = $summaries[$c->id])
                    <tr>
                        <td><a href="{{ route('cases.show', $c) }}">{{ $c->case_no }}</a></td>
                        <td data-order="{{ $c->bereavement_date->format('Y-m-d') }}">{{ $c->bereavement_date->format('d M Y') }}</td>
                        <td>{{ $c->deceased_name }} <div class="small text-muted">{{ $c->category_label }}</div></td>
                        <td>{{ $c->member->full_name }}</td>
                        <td class="text-end amount">{{ number_format($c->contribution_amount, 2) }}</td>
                        <td class="text-end amount">{{ number_format($sum['collected'], 2) }}</td>
                        <td class="text-end amount {{ $sum['outstanding'] > 0 ? 'text-danger' : '' }}">{{ number_format($sum['outstanding'], 2) }}</td>
                        <td><span class="badge {{ status_badge($c->status) }}">{{ $c->status_label }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">No welfare cases yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
