@extends('layouts.app')
@section('title', 'Beneficiaries')
@section('subtitle', 'To add or change a beneficiary, open the member\'s profile.')

@section('content')
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-auto">
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    @foreach($categories as $k => [$label])
                        <option value="{{ $k }}" @selected(request('category') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead><tr><th>Beneficiary</th><th>Category</th><th>Member</th><th>Status</th><th>Last updated</th></tr></thead>
                <tbody>
                @forelse($beneficiaries as $b)
                    <tr>
                        <td>{{ $b->name }}</td>
                        <td>{{ $b->category_label }}</td>
                        <td><a href="{{ route('members.show', $b->member_id) }}#beneficiaries">{{ $b->member->full_name }}</a></td>
                        <td>{{ $b->is_deceased ? 'Deceased' : 'Covered' }}</td>
                        <td data-order="{{ $b->updated_at->timestamp }}">{{ $b->updated_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">No beneficiaries yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
