@extends('layouts.app')
@section('title', $member->full_name)
@section('subtitle')
    {{ $member->member_no ?? 'No member number yet' }} &middot; <span class="badge {{ status_badge($member->status) }}">{{ $member->status_label }}</span>
@endsection

@section('actions')
    @if($member->status === 'pending')
        @can('members.approve')
            <form method="POST" action="{{ route('members.approve', $member) }}" data-confirm="Approve {{ $member->full_name }} and create their login account?">
                @csrf @include('partials.submission')
                <button class="btn btn-primary">Approve</button>
            </form>
            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
        @endcan
    @endif
    @can('members.manage')<a href="{{ route('members.edit', $member) }}" class="btn btn-outline-primary">Edit</a>@endcan
    @if($member->wallet)
        @can('deposits.record')<a href="{{ route('payments.create', ['member_id' => $member->id]) }}" class="btn btn-outline-primary">Record deposit</a>@endcan
        @can('wallets.view')<a href="{{ route('wallets.show', $member) }}" class="btn btn-outline-primary">Wallet</a>@endcan
    @endif
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4">@include('partials.stat', ['label' => 'Wallet balance', 'value' => money($member->balance), 'class' => $member->balance < 0 ? 'text-danger' : 'text-brand'])</div>
    <div class="col-md-4">@include('partials.stat', ['label' => 'Outstanding', 'value' => money($outstanding), 'class' => $outstanding > 0 ? 'text-danger' : ''])</div>
    <div class="col-md-4">@include('partials.stat', ['label' => 'Beneficiaries', 'value' => $beneficiaries->count()])</div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Member details</div>
            <div class="card-body">
                <dl class="dl-grid">
                    <dt>Full name</dt><dd>{{ $member->full_name }}</dd>
                    <dt>National ID</dt><dd>{{ $member->national_id }}</dd>
                    <dt>Mobile</dt><dd>{{ $member->phone }}</dd>
                    <dt>Email</dt><dd>{{ $member->email ?: '-' }}</dd>
                    <dt>Address</dt><dd>{{ $member->residential_address ?: '-' }}</dd>
                    <dt>Alternative contact</dt><dd>{{ $member->alternative_contact ?: '-' }}</dd>
                    <dt>Registered</dt><dd>{{ $member->registration_date->format('d M Y') }} ({{ $member->source }})</dd>
                    @if($member->approved_at)<dt>Approved</dt><dd>{{ $member->approved_at->format('d M Y') }} by {{ $member->approver?->name }}</dd>@endif
                    @if($member->rejection_reason)<dt>Rejection reason</dt><dd>{{ $member->rejection_reason }}</dd>@endif
                    @if($member->status_reason)<dt>Status note</dt><dd>{{ $member->status_reason }}</dd>@endif
                    <dt>Login</dt><dd>{{ $member->user ? $member->user->username.($member->user->last_login_at ? ' · last seen '.$member->user->last_login_at->diffForHumans() : ' · never signed in') : 'No account yet' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        @can('members.manage')
            @if(!in_array($member->status, ['pending', 'rejected']))
                <div class="card mb-3">
                    <div class="card-header">Membership status</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('members.status', $member) }}" data-confirm="Change this member's status?">
                            @csrf
                            <div class="mb-2">
                                <select name="status" class="form-select">
                                    @foreach(['active' => 'Active', 'suspended' => 'Suspended', 'inactive' => 'Inactive'] as $k => $label)
                                        <option value="{{ $k }}" @selected($member->status === $k)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2"><input type="text" name="reason" class="form-control" placeholder="Reason (optional)" maxlength="250"></div>
                            <button class="btn btn-outline-primary btn-sm">Update status</button>
                        </form>
                        <div class="small text-muted mt-2">Only active members are invoiced for new welfare cases and can sign in.</div>
                    </div>
                </div>
                @if($member->user)
                    <div class="card">
                        <div class="card-body d-flex justify-content-between align-items-center gap-2">
                            <span class="small">Reset password to <strong>{{ \App\Models\User::DEFAULT_PASSWORD }}</strong> and SMS login details.</span>
                            <form method="POST" action="{{ route('members.reset-password', $member) }}" data-confirm="Reset this member's password?">
                                @csrf @include('partials.submission')
                                <button class="btn btn-sm btn-outline-secondary text-nowrap">Reset</button>
                            </form>
                        </div>
                    </div>
                @endif
            @endif
        @endcan
    </div>
</div>

{{-- Beneficiaries --}}
<div class="card mb-3" id="beneficiaries">
    <div class="card-header d-flex justify-content-between align-items-center">
        Beneficiaries
        @can('beneficiaries.manage')
            <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#addBeneficiary">Add beneficiary</button>
        @endcan
    </div>
    @can('beneficiaries.manage')
        <div class="collapse {{ $errors->has('category') || $errors->has('name') ? 'show' : '' }}" id="addBeneficiary">
            <div class="card-body border-bottom">
                <form method="POST" action="{{ route('beneficiaries.store', $member) }}" class="row g-2">
                    @csrf @include('partials.submission')
                    <div class="col-md-5"><input type="text" name="name" class="form-control" placeholder="Beneficiary name" required maxlength="150"></div>
                    <div class="col-md-4">
                        <select name="category" class="form-select" required>
                            @foreach($categories as $k => [$label, $limit])
                                <option value="{{ $k }}">{{ $label }}{{ $limit ? ' (max '.$limit.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><button class="btn btn-primary w-100">Add</button></div>
                </form>
            </div>
        </div>
    @endcan
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Name</th><th>Category</th><th>Status</th>@can('beneficiaries.manage')<th class="text-end">Actions</th>@endcan</tr></thead>
            <tbody>
            @forelse($beneficiaries as $b)
                <tr>
                    <td>{{ $b->name }}</td>
                    <td>{{ $b->category_label }}</td>
                    <td>{!! $b->is_deceased ? '<span class="badge text-bg-secondary">Deceased '.e($b->date_of_death?->format('d M Y')).'</span>' : '<span class="badge text-bg-success">Covered</span>' !!}</td>
                    @can('beneficiaries.manage')
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editBen{{ $b->id }}">Edit</button>
                            @unless($b->is_deceased)
                                <form method="POST" action="{{ route('beneficiaries.destroy', $b) }}" class="d-inline" data-confirm="Remove {{ $b->name }} from this member's beneficiaries?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-light text-danger">Remove</button>
                                </form>
                            @endunless
                            <div class="modal fade text-start" id="editBen{{ $b->id }}" tabindex="-1">
                                <div class="modal-dialog"><div class="modal-content">
                                    <form method="POST" action="{{ route('beneficiaries.update', $b) }}">
                                        @csrf @method('PUT')
                                        <div class="modal-header"><h5 class="modal-title">Edit beneficiary</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <label class="form-label">Name</label>
                                            <input type="text" name="name" class="form-control mb-3" value="{{ $b->name }}" required maxlength="150">
                                            <label class="form-label">Category</label>
                                            <select name="category" class="form-select">
                                                @foreach($categories as $k => [$label])
                                                    <option value="{{ $k }}" @selected($b->category === $k)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
                                    </form>
                                </div></div>
                            </div>
                        </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="4" class="empty-state">No beneficiaries registered.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Contribution history --}}
@can('invoices.view')
<div class="card mb-3">
    <div class="card-header">Contribution history and invoices</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Invoice</th><th>Date</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Outstanding</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($invoices as $i)
                <tr>
                    <td><a href="{{ route('invoices.show', $i) }}">{{ $i->invoice_no }}</a></td>
                    <td>{{ $i->invoice_date->format('d M Y') }}</td>
                    <td>{{ $i->title }}</td>
                    <td class="text-end amount">{{ number_format($i->amount, 2) }}</td>
                    <td class="text-end amount">{{ number_format($i->amount_paid, 2) }}</td>
                    <td class="text-end amount {{ $i->outstanding > 0 ? 'text-danger' : '' }}">{{ number_format($i->outstanding, 2) }}</td>
                    <td><span class="badge {{ status_badge($i->status) }}">{{ $i->status_label }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state">No invoices yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endcan

@can('wallets.view')
<div class="card">
    <div class="card-header d-flex justify-content-between">Recent wallet transactions @if($member->wallet)<a href="{{ route('wallets.show', $member) }}" class="small fw-normal">Full statement</a>@endif</div>
    @include('wallets._ledger', ['transactions' => $transactions, 'showMember' => false])
</div>
@endcan

{{-- Reject modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('members.reject', $member) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Reject registration</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="3" required maxlength="250"></textarea>
                <div class="form-text">The applicant is informed by SMS.</div>
            </div>
            <div class="modal-footer"><button class="btn btn-danger">Reject registration</button></div>
        </form>
    </div></div>
</div>
@endsection
