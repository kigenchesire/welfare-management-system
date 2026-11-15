@extends('layouts.app')
@section('title', 'My beneficiaries')

@section('content')
<div class="alert alert-light border">
    Beneficiary records can only be changed by the Secretary. Contact the welfare office if anything needs to be corrected.
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Name</th><th>Category</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($beneficiaries as $b)
                <tr>
                    <td>{{ $b->name }}</td>
                    <td>{{ $b->category_label }}</td>
                    <td>{!! $b->is_deceased ? '<span class="badge text-bg-secondary">Deceased</span>' : '<span class="badge text-bg-success">Covered</span>' !!}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty-state">No beneficiaries registered.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
