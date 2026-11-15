<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $s['primary_color'] ?: '#1f6f5c' }}">
    <title>Offline | {{ $s['group_name'] }}</title>
    {{-- Self-contained: this page is shown when there is no connection, so it loads nothing else. --}}
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
               font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f6f8; color: #1d2733; }
        .card { background: #fff; border-radius: 14px; padding: 32px 24px; max-width: 380px; width: 100%; text-align: center;
                box-shadow: 0 6px 24px rgba(0, 0, 0, .08); }
        .icon { width: 72px; height: 72px; border-radius: 18px; margin: 0 auto 16px; display: block; }
        h1 { font-size: 1.2rem; margin: 0 0 8px; }
        p { color: #5b6773; font-size: .95rem; line-height: 1.5; margin: 0 0 20px; }
        button { background: {{ $s['primary_color'] ?: '#1f6f5c' }}; color: #fff; border: 0; border-radius: 8px; padding: 10px 22px; font-size: 1rem; cursor: pointer; }
    </style>
</head>
<body>
<div class="card">
    <img class="icon" src="{{ route('pwa.icon', ['size' => 192]) }}" alt="">
    <h1>You are offline</h1>
    <p>{{ $s['group_name'] }} needs an internet connection to show your wallet, invoices and cases. Please check your connection and try again.</p>
    <button type="button" onclick="location.reload()">Try again</button>
</div>
</body>
</html>
