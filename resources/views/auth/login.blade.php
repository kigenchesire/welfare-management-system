@extends('layouts.auth')
@section('title', 'Sign in')

@section('content')
    <h1 class="h5 fw-bold mb-1">Members Login</h1>
    <!--<p class="text-muted small mb-4">Use your National ID number, phone number or email address.</p>-->

    <form method="POST" action="{{ route('login') }}" data-no-lock>
        @csrf
        <div class="mb-3">
            <label class="form-label" for="login">National ID / phone / email</label>
            <input type="text" class="form-control @error('login') is-invalid @enderror" id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username">
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" maxlength="6" required autocomplete="current-password">
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
            <label class="form-check-label" for="remember">Keep me signed in</label>
        </div>
        <button class="btn btn-primary w-100">Sign in</button>
    </form>

    <button type="button" class="btn btn-outline-primary w-100 mt-2 d-none" data-install-app>
        <i class="bi bi-download me-1"></i>Install app on this device
    </button>

    <p class="small text-muted mt-4 mb-0">
        Forgot your password? Contact the Secretary at {{ $s['phone'] }} to have it reset.
        {{-- Public website disabled for now:
        Not a member yet? <a href="{{ route('home') }}#register">Register here</a>. --}}
    </p>
@endsection
