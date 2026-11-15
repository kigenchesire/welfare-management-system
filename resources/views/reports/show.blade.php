@extends('layouts.app')
@section('title', $report['title'])
@section('subtitle', ($report['subtitle'] ?? '').(isset($report['subtitle']) ? ' · ' : '').$report['period'].' · '.count($report['rows']).' row(s)')

@section('actions')
    <a href="{{ route('reports.index') }}" class="btn btn-light">All reports</a>
    <button onclick="window.print()" class="btn btn-outline-secondary">Print</button>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-outline-primary">PDF</a>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-outline-primary">Excel</a>
@endsection

@section('content')
<div class="print-only mb-2">
    <strong>{{ $s['group_name'] }}</strong> &middot; {{ $report['title'] }} &middot; {{ $report['period'] }} &middot; Printed {{ now()->format('d M Y H:i') }}
</div>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            @if($report['key'] === 'case-contributions')
                <div class="col-md-6">
                    <label class="form-label small">Welfare case</label>
                    <select name="case_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Select a case...</option>
                        @foreach($report['cases'] as $id => $label)
                            <option value="{{ $id }}" @selected((string) request('case_id') === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif(!in_array($report['key'], ['wallet-balances']))
                <div class="col-6 col-md-2">
                    <label class="form-label small">From</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">To</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control">
                </div>
            @endif
            @foreach($report['filters'] ?? [] as $name => $options)
                <div class="col-md-2">
                    <label class="form-label small">{{ ucfirst($name) }}</label>
                    <select name="{{ $name }}" class="form-select">
                        <option value="">All</option>
                        @foreach($options as $k => $label)
                            <option value="{{ $k }}" @selected(request($name) === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-md-3">
                <label class="form-label small">Search</label>
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, ID, reference...">
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-primary">Apply</button>
                <a href="{{ route('reports.show', $report['key']) }}" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover {{ count($report['rows']) ? 'datatable' : '' }}" data-page-length="50">
                <thead>
                <tr>
                    @foreach($report['columns'] as $i => $col)
                        <th class="{{ in_array($i, $report['numeric']) ? 'text-end' : '' }}">{{ $col }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        @foreach($row as $i => $cell)
                            <td class="{{ in_array($i, $report['numeric']) ? 'text-end amount' : '' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($report['columns']) }}" class="empty-state">No records match the filters.</td></tr>
                @endforelse
                </tbody>
                @if(count($report['rows']) && $report['numeric'] && $report['key'] !== 'financial-summary')
                    <tfoot>
                    <tr class="fw-bold">
                        @foreach($report['columns'] as $i => $col)
                            <td class="{{ in_array($i, $report['numeric']) ? 'text-end amount' : '' }}">
                                @if($i === 0)Total@elseif(in_array($i, $report['numeric']))
                                    {{ number_format(collect($report['rows'])->sum(fn ($r) => (float) str_replace([',', '+'], '', $r[$i])), 2) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
