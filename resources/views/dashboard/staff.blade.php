@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', 'Welcome back, '.$user->name.'.')

@section('actions')
    @can('members.manage')<a href="{{ route('members.create') }}" class="btn btn-outline-primary">Register member</a>@endcan
    @can('cases.manage')<a href="{{ route('cases.create') }}" class="btn btn-outline-primary">New welfare case</a>@endcan
    @can('deposits.record')<a href="{{ route('payments.create') }}" class="btn btn-primary">Record deposit</a>@endcan
@endsection

@section('content')

{{-- Administrator / Chairperson overview --}}
@isset($overview)
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Registered members', 'value' => number_format($overview['members']), 'hint' => number_format($overview['active']).' active'])</div>
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Pending registrations', 'value' => number_format($overview['pending']), 'hint' => '<a href="'.route('members.pending').'">Review</a>'])</div>
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Welfare cases', 'value' => number_format($overview['cases']), 'hint' => $overview['open_cases'].' open, '.$overview['closed_cases'].' closed'])</div>
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Total deposits', 'value' => money($overview['deposits'])])</div>
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Member wallet balances', 'value' => money($overview['wallets']), 'hint' => 'Held on behalf of members'])</div>
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Outstanding contributions', 'value' => money($overview['outstanding']), 'class' => $overview['outstanding'] > 0 ? 'text-danger' : ''])</div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">Deposits vs. collections (12 months)</div>
                <div class="card-body"><canvas id="trendChart" height="130"></canvas></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">Recent activity
                    @can('audit.view')<a href="{{ route('admin.audit') }}" class="small fw-normal">All</a>@endcan
                </div>
                <ul class="list-group list-group-flush small">
                    @forelse($activities as $a)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <span><strong>{{ $a->user_name }}</strong> &middot; {{ str_replace(['.', '_'], ' ', $a->action) }}</span>
                                <span class="text-muted text-nowrap">{{ $a->created_at->diffForHumans(null, true) }}</span>
                            </div>
                            @if($a->description)<div class="text-muted text-truncate">{{ $a->description }}</div>@endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No activity yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            new Chart(document.getElementById('trendChart'), {
                type: 'bar',
                data: {
                    labels: @json($trend['labels']),
                    datasets: [
                        { label: 'Deposits', data: @json($trend['deposits']), backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--brand').trim() },
                        { label: 'Contributions collected', data: @json($trend['contributions']), backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--brand-2').trim() }
                    ]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
            });
        </script>
    @endpush
@endisset

{{-- Secretary --}}
@isset($secretary)
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Pending registrations', 'value' => $secretary['pending_count'], 'class' => $secretary['pending_count'] ? 'text-warning' : ''])</div>
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Active members', 'value' => number_format($secretary['members'])])</div>
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Open cases', 'value' => $secretary['open_cases']])</div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">Pending registrations <a href="{{ route('members.pending') }}" class="small fw-normal">All</a></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        @forelse($secretary['pending'] as $m)
                            <tr>
                                <td><a href="{{ route('members.show', $m) }}">{{ $m->full_name }}</a><div class="small text-muted">ID {{ $m->national_id }} &middot; {{ $m->phone }}</div></td>
                                <td class="text-end small text-muted">{{ $m->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No pending registrations.</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Recent beneficiary updates</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        @forelse($secretary['beneficiaries'] as $b)
                            <tr>
                                <td>{{ $b->name }} <span class="small text-muted">({{ $b->category_label }})</span><div class="small text-muted">{{ $b->member->full_name }}</div></td>
                                <td class="text-end small text-muted">{{ $b->updated_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No beneficiaries yet.</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
        </div>
    </div>
@endisset

{{-- Treasurer --}}
@isset($treasurer)
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Wallet balances', 'value' => money($treasurer['wallets'])])</div>
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Outstanding invoices', 'value' => number_format($treasurer['outstanding_invoices'])])</div>
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Unpaid contributions', 'value' => money($treasurer['unpaid_welfare']), 'class' => 'text-danger'])</div>
        <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'Unpaid custom invoices', 'value' => money($treasurer['unpaid_custom'])])</div>
    </div>
    <div class="card mb-3">
        <div class="card-header">This month</div>
        <div class="card-body">
            <div class="row text-center g-3">
                <div class="col-6 col-md-3"><div class="small text-muted">Deposits</div><div class="fw-bold amount">{{ money($treasurer['month']['deposits']) }}</div></div>
                <div class="col-6 col-md-3"><div class="small text-muted">Contributions collected</div><div class="fw-bold amount">{{ money($treasurer['month']['welfare_collected']) }}</div></div>
                <div class="col-6 col-md-3"><div class="small text-muted">Custom collected</div><div class="fw-bold amount">{{ money($treasurer['month']['custom_collected']) }}</div></div>
                <div class="col-6 col-md-3"><div class="small text-muted">Refunds</div><div class="fw-bold amount">{{ money($treasurer['month']['refunds']) }}</div></div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">Recent deposits <a href="{{ route('payments.index') }}" class="small fw-normal">All</a></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        @forelse($treasurer['deposits'] as $p)
                            <tr>
                                <td><a href="{{ route('payments.show', $p) }}">{{ $p->member->full_name }}</a><div class="small text-muted">{{ $p->reference }} &middot; {{ $p->payment_date->format('d M Y') }}</div></td>
                                <td class="text-end amount">{{ money($p->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No deposits yet.</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">Recent transactions <a href="{{ route('transactions.index') }}" class="small fw-normal">All</a></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        @forelse($treasurer['transactions'] as $t)
                            <tr>
                                <td>{{ $t->member->full_name }}<div class="small text-muted">{{ $t->type_label }} &middot; {{ $t->transaction_date->format('d M Y') }}</div></td>
                                <td class="text-end amount {{ $t->direction }}">{{ $t->direction === 'credit' ? '+' : '-' }}{{ number_format($t->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No transactions yet.</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
        </div>
    </div>
@endisset

{{-- Recent cases (all staff) --}}
@can('cases.view')
<div class="card">
    <div class="card-header d-flex justify-content-between">Recent welfare cases <a href="{{ route('cases.index') }}" class="small fw-normal">All</a></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Case</th><th>Deceased</th><th>Member</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($recentCases as $c)
                <tr>
                    <td><a href="{{ route('cases.show', $c) }}">{{ $c->case_no }}</a></td>
                    <td>{{ $c->deceased_name }} <span class="small text-muted">({{ $c->category_label }})</span></td>
                    <td>{{ $c->member->full_name }}</td>
                    <td>{{ $c->bereavement_date->format('d M Y') }}</td>
                    <td><span class="badge {{ status_badge($c->status) }}">{{ $c->status_label }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-state">No welfare cases yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endcan
@endsection
