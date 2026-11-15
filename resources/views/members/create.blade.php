@extends('layouts.app')
@section('title', 'Register member')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('members.store') }}">
            @csrf
            @include('partials.submission')
            @include('members._fields', ['member' => null, 'withDate' => true])

            <h2 class="h6 fw-bold mt-4 mb-1">Beneficiaries</h2>
            <p class="small text-muted">Names only. Up to 2 parents, 2 parents-in-law, 1 spouse and any number of children.</p>
            @include('members._beneficiary_rows', ['categories' => $categories])

            @can('members.approve')
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="approve_now" id="approve_now" value="1" @checked(old('approve_now', true))>
                    <label class="form-check-label" for="approve_now">Approve immediately and create the member's login (password {{ \App\Models\User::DEFAULT_PASSWORD }})</label>
                </div>
            @endcan
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary">Save member</button>
                <a href="{{ route('members.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
