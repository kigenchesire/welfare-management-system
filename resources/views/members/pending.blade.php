@extends('layouts.app')
@section('title', 'Pending registrations')
@section('subtitle', 'Review each application, then approve or reject it.')

@section('content')
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Applied</th><th>Name</th><th>National ID</th><th>Phone</th><th>Beneficiaries</th><th>Source</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($members as $m)
                <tr>
                    <td class="text-nowrap">{{ $m->created_at->format('d M Y') }}</td>
                    <td><a href="{{ route('members.show', $m) }}">{{ $m->full_name }}</a></td>
                    <td>{{ $m->national_id }}</td>
                    <td>{{ $m->phone }}</td>
                    <td>{{ $m->beneficiaries_count }}</td>
                    <td>{{ ucfirst($m->source) }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('members.show', $m) }}" class="btn btn-sm btn-outline-primary">Review</a>
                        @can('members.approve')
                            <form method="POST" action="{{ route('members.approve', $m) }}" class="d-inline" data-confirm="Approve {{ $m->full_name }} and create their login account?">
                                @csrf @include('partials.submission')
                                <button class="btn btn-sm btn-primary">Approve</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state">There are no pending registrations.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
