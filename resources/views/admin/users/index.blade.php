@extends('layouts.app')
@section('title', 'Users')
@section('subtitle', 'Staff and member login accounts. Member accounts are created automatically on approval.')

@section('actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">New user</a>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Roles</th><th>Status</th><th>Last login</th><th class="no-sort"></th></tr></thead>
                <tbody>
                @foreach($users as $u)
                    <tr>
                        <td>{{ $u->name }}@if($u->member)<div class="small text-muted">Member {{ $u->member->member_no }}</div>@endif</td>
                        <td>{{ $u->username }}</td>
                        <td>{{ $u->email ?: '-' }}</td>
                        <td>{{ $u->roles->pluck('name')->implode(', ') }}</td>
                        <td><span class="badge {{ $u->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $u->is_active ? 'Active' : 'Disabled' }}</span></td>
                        <td data-order="{{ $u->last_login_at?->timestamp ?? 0 }}" class="small">{{ $u->last_login_at?->format('d M Y H:i') ?? 'Never' }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-light">Edit</a>
                            <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" class="d-inline" data-confirm="Reset {{ $u->name }}'s password to {{ \App\Models\User::DEFAULT_PASSWORD }}?">
                                @csrf @include('partials.submission')
                                <button class="btn btn-sm btn-light">Reset password</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
