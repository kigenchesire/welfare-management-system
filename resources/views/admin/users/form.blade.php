@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'New user')

@section('content')
<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
                    @csrf
                    @if($user->exists) @method('PUT') @else @include('partials.submission') @endif
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Full name</label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="username">Username</label>
                            <input type="text" name="username" id="username" class="form-control" value="{{ old('username', $user->username) }}" required maxlength="100">
                            <div class="form-text">Used to sign in (members use their National ID).</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $user->email) }}" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $user->phone) }}" maxlength="20">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Roles</label>
                            <div class="d-flex flex-wrap gap-3">
                                @php($current = old('roles', $user->exists ? $user->getRoleNames()->all() : []))
                                @foreach($roles as $role)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role }}" id="role_{{ $loop->index }}" @checked(in_array($role, $current))>
                                        <label class="form-check-label" for="role_{{ $loop->index }}">{{ $role }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $user->is_active))>
                                <label class="form-check-label" for="is_active">Account active (can sign in)</label>
                            </div>
                        </div>
                    </div>
                    @unless($user->exists)
                        <div class="alert alert-light border small mt-3 mb-0">The new account's password will be <strong>{{ \App\Models\User::DEFAULT_PASSWORD }}</strong>. Ask the user to change it after signing in.</div>
                    @endunless
                    <div class="mt-4 d-flex gap-2">
                        <button class="btn btn-primary">Save</button>
                        <a href="{{ route('admin.users') }}" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
