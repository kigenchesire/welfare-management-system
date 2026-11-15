<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@hasSection('title')@yield('title') | @endif{{ $s['group_name'] }}</title>
@if($s['favicon'])
    <link rel="icon" href="{{ \App\Support\Settings::image('favicon') }}">
@endif
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
