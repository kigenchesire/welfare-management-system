<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $payment->receipt_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        .table td { border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    @include('payments._receipt_body')
</body>
</html>
