@extends('layouts.app')
@section('title', 'System settings')
@section('subtitle', 'Everything specific to this welfare group is configured here. No code changes are needed to rebrand.')

@section('actions')
    @can('website.manage')<a href="{{ route('admin.website') }}" class="btn btn-outline-primary">Website management</a>@endcan
@endsection

@section('content')
@php($tabs = ['general' => 'General', 'financial' => 'Financial', 'sms' => 'SMS Gateway'])
<ul class="nav nav-tabs mb-3">
    @foreach($tabs as $k => $label)
        <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ route('admin.settings', ['tab' => $k]) }}">{{ $label }}</a></li>
    @endforeach
</ul>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <input type="hidden" name="group" value="{{ $tab }}">

            @if($tab === 'financial')
                <div class="alert alert-light border small">
                    Contribution amounts apply to new welfare cases only. Existing cases keep the amount they were created with.
                </div>
            @elseif($tab === 'sms')
                <div class="alert alert-light border small">
                    Template placeholders: {name} {group} {currency} {amount} {outstanding} {balance} {reference} {paybill} {case_no} {deceased} {category} {member} {invoice_no} {title} {member_no} {username} {password} {login_url}.
                    SMS failures never stop a payment or case from being saved; they are recorded in SMS Logs.
                </div>
            @endif

            <div class="row g-3">
                @foreach($definitions as $key => $def)
                    @if($def[0] === $tab)
                        @include('admin._setting_field', ['key' => $key, 'def' => $def])
                    @endif
                @endforeach
            </div>
            <button class="btn btn-primary mt-4">Save {{ strtolower($tabs[$tab]) }} settings</button>
        </form>
    </div>
</div>

@if($tab === 'sms')
    <div class="card mt-3">
        <div class="card-header">Send a test SMS</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.test-sms') }}" class="row g-2">
                @csrf
                <div class="col-md-4"><input type="text" name="phone" class="form-control" placeholder="07XX XXX XXX" required></div>
                <div class="col-auto"><button class="btn btn-outline-primary">Send test</button></div>
            </form>
        </div>
    </div>
@endif
@endsection
