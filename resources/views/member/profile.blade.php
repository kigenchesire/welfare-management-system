@extends('layouts.app')
@section('title', 'My profile')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <dl class="dl-grid">
                    <dt>Member number</dt><dd>{{ $member->member_no }}</dd>
                    <dt>Full name</dt><dd>{{ $member->full_name }}</dd>
                    <dt>National ID</dt><dd>{{ $member->national_id }}</dd>
                    <dt>Mobile number</dt><dd>{{ $member->phone }}</dd>
                    <dt>Email</dt><dd>{{ $member->email ?: '-' }}</dd>
                    <dt>Residential address</dt><dd>{{ $member->residential_address ?: '-' }}</dd>
                    <dt>Alternative contact</dt><dd>{{ $member->alternative_contact ?: '-' }}</dd>
                    <dt>Registered on</dt><dd>{{ $member->registration_date->format('d M Y') }}</dd>
                    <dt>Status</dt><dd><span class="badge {{ status_badge($member->status) }}">{{ $member->status_label }}</span></dd>
                    <dt>Wallet balance</dt><dd class="amount fw-semibold">{{ money($member->balance) }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <p class="mb-2">To change any of these details, please contact the Secretary.</p>
                <p class="small text-muted mb-3">{{ $s['phone'] }} &middot; {{ $s['email'] }}</p>
                <a href="{{ route('password.change') }}" class="btn btn-outline-primary">Change password</a>
            </div>
        </div>
    </div>
</div>
@endsection
