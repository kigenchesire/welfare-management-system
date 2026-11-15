@extends('layouts.app')
@section('title', 'My dashboard')
@section('subtitle', 'Member No '.$member->member_no.' · '.$member->status_label)

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4">@include('partials.stat', ['label' => 'Wallet balance', 'value' => money($member->balance), 'class' => $member->balance < 0 ? 'text-danger' : 'text-brand', 'hint' => '<a href="'.route('member.wallet').'">View statement</a>'])</div>
    <div class="col-md-4">@include('partials.stat', ['label' => 'Outstanding contributions', 'value' => money($outstanding), 'class' => $outstanding > 0 ? 'text-danger' : '', 'hint' => '<a href="'.route('member.invoices').'">View invoices</a>'])</div>
    <div class="col-md-4">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="label">How to pay</div>
            <div class="fw-semibold mt-1">{{ $s['payment_method'] }}: {{ $s['paybill_number'] }}</div>
            <div class="small text-muted">{{ $s['account_instructions'] }}</div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">My details</div>
            <div class="card-body">
                <dl class="dl-grid">
                    <dt>Name</dt><dd>{{ $member->full_name }}</dd>
                    <dt>National ID</dt><dd>{{ $member->national_id }}</dd>
                    <dt>Phone</dt><dd>{{ $member->phone }}</dd>
                    <dt>Member since</dt><dd>{{ $member->registration_date->format('d M Y') }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">Outstanding invoices <a href="{{ route('member.invoices') }}" class="small fw-normal">All</a></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    @forelse($openInvoices as $i)
                        <tr>
                            <td><a href="{{ route('member.invoice', $i->id) }}">{{ $i->invoice_no }}</a><div class="small text-muted">{{ $i->title }}</div></td>
                            <td class="text-end amount text-danger">{{ money($i->outstanding) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">You have no outstanding invoices.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">Recent payments <a href="{{ route('member.payments') }}" class="small fw-normal">All</a></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    @forelse($payments as $p)
                        <tr>
                            <td>{{ $p->payment_date->format('d M Y') }}<div class="small text-muted">{{ $p->reference }}</div></td>
                            <td class="text-end amount">{{ money($p->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">No payments recorded yet.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">Recent welfare cases <a href="{{ route('member.cases') }}" class="small fw-normal">All</a></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    @forelse($cases as $c)
                        <tr>
                            <td>{{ $c->deceased_name }} <span class="small text-muted">({{ $c->category_label }})</span><div class="small text-muted">Member: {{ $c->member->full_name }}</div></td>
                            <td class="text-end small">{{ $c->bereavement_date->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">No welfare cases.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
