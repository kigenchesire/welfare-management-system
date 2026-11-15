@extends('layouts.app')
@section('title', 'Change password')

@section('content')
<div class="row">
    <div class="col-md-7 col-lg-5">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" maxlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">New password</label>
                        <input type="password" class="form-control" id="password" name="password" maxlength="6" required>
                        <div class="form-text">Up to 6 characters. Any letters, numbers or symbols.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password_confirmation">Confirm new password</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" maxlength="6" required>
                    </div>
                    <button class="btn btn-primary">Change password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
