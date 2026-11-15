@extends('layouts.app')
@section('title', 'Receipt '.$payment->receipt_no)

@section('actions')
    <button onclick="window.print()" class="btn btn-outline-secondary">Print</button>
    <a href="{{ $portal ? route('member.receipt', [$payment->id, 'pdf' => 1]) : route('payments.show', [$payment, 'pdf' => 1]) }}" class="btn btn-outline-primary">Download PDF</a>
    @if(!$portal && $payment->status === 'confirmed')
        @can('deposits.record')
            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseModal">Reverse</button>
        @endcan
    @endif
@endsection

@section('content')
<div class="card" style="max-width: 720px;">
    <div class="card-body p-4">
        @include('payments._receipt_body')
    </div>
</div>

@if(!$portal && $payment->transactions->count())
    <div class="card mt-3" style="max-width: 720px;">
        <div class="card-header">Ledger entries</div>
        <div class="table-responsive">
            <table class="table mb-0">
                @foreach($payment->transactions as $t)
                    <tr><td>{{ $t->reference }}</td><td>{{ $t->type_label }}</td><td class="text-end amount {{ $t->direction }}">{{ $t->direction === 'credit' ? '+' : '-' }}{{ number_format($t->amount, 2) }}</td></tr>
                @endforeach
            </table>
        </div>
    </div>
@endif

@if(!$portal)
<div class="modal fade" id="reverseModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('payments.reverse', $payment) }}" data-confirm="Reverse this payment?">
            @csrf @include('partials.submission')
            <div class="modal-header"><h5 class="modal-title">Reverse payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small">A reversal debit of {{ money($payment->amount) }} will be posted to the member's wallet. The original record is kept.</p>
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control mb-2" rows="2" required maxlength="250"></textarea>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="authorize_negative" value="1" id="revNeg">
                    <label class="form-check-label small" for="revNeg">Authorise a negative balance if the money has already been used</label>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-danger">Reverse payment</button></div>
        </form>
    </div></div>
</div>
@endif
@endsection
