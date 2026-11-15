<!doctype html>
<html lang="en">
<head>
    @include('partials.head')
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="text-center mb-4">
            <a href="{{ route('home') }}" class="text-decoration-none text-reset">
                @if($logo = \App\Support\Settings::image('logo'))
                    <img src="{{ $logo }}" alt="Logo" class="auth-logo mb-2">
                @endif
                <div class="h5 fw-bold mb-0">{{ $s['group_name'] }}</div>
            </a>
        </div>
        <div class="card">
            <div class="card-body p-4">
                @include('partials.alerts')
                @yield('content')
            </div>
        </div>
        {{-- Public website disabled for now:
        <div class="text-center mt-3 small"><a href="{{ route('home') }}">&larr; Back to website</a></div> --}}
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/app.js') }}?v=1"></script>
</body>
</html>
