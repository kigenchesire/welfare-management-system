<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@hasSection('title')@yield('title') | @endif{{ $s['group_name'] }}</title>
@if($s['favicon'])
    <link rel="icon" href="{{ \App\Support\Settings::image('favicon') }}">
@else
    <link rel="icon" type="image/png" href="{{ route('pwa.icon', ['size' => 192]) }}">
@endif
{{-- Installable app (PWA) --}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ $s['primary_color'] ?: '#1f6f5c' }}">
<meta name="application-name" content="{{ $s['short_name'] ?: $s['group_name'] }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $s['short_name'] ?: $s['group_name'] }}">
<link rel="apple-touch-icon" href="{{ route('pwa.icon', ['size' => 180]) }}">
<meta name="sw-url" content="{{ asset('sw.js') }}" data-scope="{{ url('/') }}/">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    :root {
        --brand: {{ $s['primary_color'] ?: '#1f6f5c' }};
        --brand-2: {{ $s['secondary_color'] ?: '#c89b3c' }};
    }
</style>
<link href="{{ asset('css/app.css') }}?v=1" rel="stylesheet">
