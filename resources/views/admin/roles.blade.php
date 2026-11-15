@extends('layouts.app')
@section('title', 'Roles & permissions')
@section('subtitle', 'Permissions are enforced on the server for every page and action.')

@section('content')
<ul class="nav nav-tabs mb-3" role="tablist">
    @foreach($roles as $role)
        <li class="nav-item">
            <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#role{{ $role->id }}" type="button">
                {{ $role->name }} <span class="badge text-bg-light">{{ $role->users_count }}</span>
            </button>
        </li>
    @endforeach
</ul>
<div class="tab-content">
    @foreach($roles as $role)
        @php($has = $role->permissions->pluck('name')->all())
        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="role{{ $role->id }}">
            <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                @csrf @method('PUT')
                @if($role->name === 'Administrator')
                    <div class="alert alert-light border">Administrators always have full access.</div>
                @elseif($role->name === 'Member')
                    <div class="alert alert-light border">Members always have access to their own profile, wallet, invoices, payments, beneficiaries and welfare cases. Staff permissions are not normally given to this role.</div>
                @endif
                <div class="row g-3">
                    @foreach($groups as $group => $perms)
                        <div class="col-md-6 col-xl-4">
                            <div class="card h-100">
                                <div class="card-header">{{ $group }}</div>
                                <div class="card-body">
                                    @foreach($perms as $name => $label)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $name }}" id="p{{ $role->id }}_{{ $loop->parent->index }}_{{ $loop->index }}"
                                                @checked($role->name === 'Administrator' || in_array($name, $has)) @disabled($role->name === 'Administrator')>
                                            <label class="form-check-label" for="p{{ $role->id }}_{{ $loop->parent->index }}_{{ $loop->index }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @unless($role->name === 'Administrator')
                    <button class="btn btn-primary mt-3">Save {{ $role->name }} permissions</button>
                @endunless
            </form>
        </div>
    @endforeach
</div>
@endsection
