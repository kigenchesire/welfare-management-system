@extends('layouts.app')
@section('title', 'Financial overview')
@section('subtitle', ($from || $to) ? 'Period: '.($from ?: 'start').' to '.($to ?: 'today') : 'All dates')

@section('actions')
    @can('reports.financial')
        <a href="{{ route('reports.show', ['financial-summary', 'from' => $from, 'to' => $to]) }}" class="btn btn-outline-primary">Export summary</a>
    @endcan
@endsection

@section('content')
<form class="row g-2 mb-3" method="GET">
    <div class="col-6 col-md-3"><input type="date" name="from" value="{{ $from }}" class="form-control" aria-label="From"></div>
    <div class="col-6 col-md-3"><input type="date" name="to" value="{{ $to }}" class="form-control" aria-label="To"></div>
    <div class="col-auto"><button class="btn btn-primary">Apply</button></div>
    @if($from || $to)<div class="col-auto"><a href="{{ route('finance.summary') }}" class="btn btn-light">Clear</a></div>@endif
</form>

<h2 class="h6 text-muted text-uppercase fw-semibold mt-2">Member funds (liability)</h2>
<div class="row g-3 mb-4">
    <div class="col-md-4">@include('partials.stat', ['label' => 'Total wallet balances', 'value' => money($summary['wallet_balances']), 'hint' => 'Money held on behalf of members, as at today. Not income.'])</div>
    <div class="col-6 col-md-4">@include('partials.stat', ['label' => 'Deposits received', 'value' => money($summary['deposits']), 'hint' => $summary['deposit_count'].' deposit(s)'])</div>
    <div class="col-6 col-md-4">@include('partials.stat', ['label' => 'Deposits reversed', 'value' => money($summary['deposits_reversed'])])</div>
</div>

<h2 class="h6 text-muted text-uppercase fw-semibold">Welfare collections</h2>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">@include('partials.stat', ['label' => 'Contributions collected', 'value' => money($summary['welfare_collected']), 'class' => 'text-brand', 'hint' => 'Net of refunds'])</div>
    <div class="col-6 col-md-4">@include('partials.stat', ['label' => 'Contributions outstanding', 'value' => money($summary['welfare_outstanding']), 'class' => 'text-danger'])</div>
    <div class="col-md-4">@include('partials.stat', ['label' => 'Total collected (all invoices)', 'value' => money($summary['total_collected'])])</div>
</div>

<h2 class="h6 text-muted text-uppercase fw-semibold">Case funds (all dates)</h2>
<div class="row g-3 mb-4">
    <div class="col-md-4">@include('partials.stat', ['label' => 'Welfare fund collected', 'value' => money($summary['fund_welfare']), 'hint' => 'Disbursed '.money($summary['welfare_disbursed']).' · held '.money($summary['welfare_held'])])</div>
    <div class="col-6 col-md-4">@include('partials.stat', ['label' => 'Transport collected', 'value' => money($summary['fund_transport']), 'hint' => 'Disbursed '.money($summary['transport_disbursed']).' · held '.money($summary['transport_held'])])</div>
    <div class="col-6 col-md-4">@include('partials.stat', ['label' => 'Office account balance', 'value' => money($summary['office_balance']), 'class' => 'text-brand', 'hint' => 'From cases '.money($summary['fund_office']).' · top-ups '.money($summary['office_topups']).' · expenses '.money($summary['office_expenses'])])</div>
</div>

<h2 class="h6 text-muted text-uppercase fw-semibold">Custom invoices</h2>
<div class="row g-3 mb-4">
    <div class="col-md-4">@include('partials.stat', ['label' => 'Raised', 'value' => money($summary['custom_invoiced'])])</div>
    <div class="col-6 col-md-4">@include('partials.stat', ['label' => 'Collected', 'value' => money($summary['custom_collected'])])</div>
    <div class="col-6 col-md-4">@include('partials.stat', ['label' => 'Outstanding', 'value' => money($summary['custom_outstanding'])])</div>
</div>

<h2 class="h6 text-muted text-uppercase fw-semibold">Corrections</h2>
<div class="row g-3 mb-4">
    <div class="col-md-4">@include('partials.stat', ['label' => 'Refunds', 'value' => money($summary['refunds'])])</div>
    <div class="col-6 col-md-4">@include('partials.stat', ['label' => 'Adjustments (credit)', 'value' => money($summary['adjustments_credit'])])</div>
    <div class="col-6 col-md-4">@include('partials.stat', ['label' => 'Adjustments (debit)', 'value' => money($summary['adjustments_debit'])])</div>
</div>

<div class="card">
    <div class="card-header">Last 12 months</div>
    <div class="card-body"><canvas id="trendChart" height="110"></canvas></div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    var css = getComputedStyle(document.documentElement);
    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: @json($trend['labels']),
            datasets: [
                { label: 'Deposits', data: @json($trend['deposits']), backgroundColor: css.getPropertyValue('--brand').trim() },
                { label: 'Collected from wallets', data: @json($trend['contributions']), backgroundColor: css.getPropertyValue('--brand-2').trim() }
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
    });
</script>
@endpush
