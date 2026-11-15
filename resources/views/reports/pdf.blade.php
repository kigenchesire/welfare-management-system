<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .meta { color: #666; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f0f3f2; text-align: left; font-size: 8px; text-transform: uppercase; }
        th, td { border: 1px solid #ddd; padding: 4px 5px; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; }
    </style>
</head>
<body>
    <div><strong>{{ $s['group_name'] }}</strong></div>
    <h1>{{ $report['title'] }}</h1>
    <div class="meta">{{ $report['subtitle'] ?? '' }} {{ $report['period'] }} &middot; {{ count($report['rows']) }} row(s) &middot; Generated {{ now()->format('d M Y H:i') }}</div>
    <table>
        <thead>
        <tr>@foreach($report['columns'] as $i => $col)<th class="{{ in_array($i, $report['numeric']) ? 'num' : '' }}">{{ $col }}</th>@endforeach</tr>
        </thead>
        <tbody>
        @forelse($report['rows'] as $row)
            <tr>@foreach($row as $i => $cell)<td class="{{ in_array($i, $report['numeric']) ? 'num' : '' }}">{{ $cell }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($report['columns']) }}">No records.</td></tr>
        @endforelse
        </tbody>
        @if(count($report['rows']) && $report['numeric'] && $report['key'] !== 'financial-summary')
            <tfoot>
            <tr>
                @foreach($report['columns'] as $i => $col)
                    <td class="{{ in_array($i, $report['numeric']) ? 'num' : '' }}">
                        @if($i === 0)Total@elseif(in_array($i, $report['numeric'])){{ number_format(collect($report['rows'])->sum(fn ($r) => (float) str_replace([',', '+'], '', $r[$i])), 2) }}@endif
                    </td>
                @endforeach
            </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
