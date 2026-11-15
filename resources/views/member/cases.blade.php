@extends('layouts.app')
@section('title', 'Welfare cases')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable">
                <thead><tr><th>Case</th><th>Date</th><th>Deceased</th><th>Member</th><th class="text-end">Contribution</th><th>My invoice</th></tr></thead>
                <tbody>
                @forelse($cases as $c)
                    @php($inv = $myInvoices[$c->id] ?? null)
                    <tr>
                        <td>{{ $c->case_no }}</td>
                        <td data-order="{{ $c->bereavement_date->format('Y-m-d') }}">{{ $c->bereavement_date->format('d M Y') }}</td>
                        <td>{{ $c->deceased_name }} <span class="small text-muted">({{ $c->category_label }})</span></td>
                        <td>{{ $c->member->full_name }}</td>
                        <td class="text-end amount">{{ number_format($c->contribution_amount, 2) }}</td>
                        <td>
                            @if($inv)
                                <a href="{{ route('member.invoice', $inv->id) }}" class="badge {{ status_badge($inv->status) }} text-decoration-none">{{ $inv->status_label }}</a>
                            @else
                                <span class="small text-muted">Not invoiced</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No welfare cases yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
