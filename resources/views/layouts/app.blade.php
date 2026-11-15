<!doctype html>
<html lang="en">
<head>
    @include('partials.head')
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body>
@php($u = auth()->user())

@include('partials.sidebar')
<div class="sidebar-backdrop"></div>

<div class="app-main">
    <header class="app-topbar d-flex align-items-center gap-2">
        <button class="btn btn-light d-lg-none" type="button" data-sidebar-toggle aria-label="Menu"><i class="bi bi-list"></i></button>
        <div class="fw-semibold text-truncate d-none d-sm-block">{{ $s['group_name'] }}</div>

        <div class="ms-auto d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary d-none" data-install-app title="Install this system as an app on this device">
                <i class="bi bi-download"></i><span class="d-none d-md-inline ms-1">Install app</span>
            </button>
            <div class="dropdown">
                <button class="btn btn-light position-relative" data-bs-toggle="dropdown" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    @if($unreadNotifications->count())
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unreadNotifications->count() }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 320px;">
                    <div class="px-3 py-2 border-bottom fw-semibold">Notifications</div>
                    @forelse($unreadNotifications as $n)
                        <a class="dropdown-item py-2 text-wrap" href="{{ route('notifications.read', $n) }}">
                            <div class="fw-semibold small">{{ $n->title }}</div>
                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($n->message, 90) }}</div>
                            <div class="small text-muted">{{ $n->created_at->diffForHumans() }}</div>
                        </a>
                    @empty
                        <div class="px-3 py-3 small text-muted">No new notifications.</div>
                    @endforelse
                    <a class="dropdown-item text-center small py-2 border-top" href="{{ route('notifications') }}">View all</a>
                </div>
            </div>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i><span class="d-none d-md-inline">{{ $u->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-muted">{{ $u->getRoleNames()->implode(', ') }}</span></li>
                    @if($u->member)
                        <li><a class="dropdown-item" href="{{ route('member.profile') }}">My profile</a></li>
                    @endif
                    <li><a class="dropdown-item" href="{{ route('password.change') }}">Change password</a></li>
                    {{-- Public website disabled for now:
                    <li><a class="dropdown-item" href="{{ route('home') }}" target="_blank">View website</a></li> --}}
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}" data-no-lock>@csrf
                            <button class="dropdown-item text-danger">Sign out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main class="app-content">
        @if($u->must_change_password && !request()->routeIs('password.change'))
            <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 no-print">
                <span>You are using the default password. Please change it to keep your account secure.</span>
                <a href="{{ route('password.change') }}" class="btn btn-sm btn-warning">Change password</a>
            </div>
        @endif

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h1 class="page-title">@yield('title')</h1>
                @hasSection('subtitle')<div class="text-muted small">@yield('subtitle')</div>@endif
            </div>
            <div class="d-flex flex-wrap gap-2 no-print">@yield('actions')</div>
        </div>

        @include('partials.alerts')
        @yield('content')
    </main>

    <footer class="px-3 py-3 small text-muted border-top bg-white no-print">
        &copy; {{ date('Y') }} {{ $s['group_name'] }}
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/js/dataTables.bootstrap5.min.js"></script>
@stack('scripts-before')
<script src="{{ asset('js/app.js') }}?v=2"></script>
@stack('scripts')
</body>
</html>
