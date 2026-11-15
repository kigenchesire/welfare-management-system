@extends('layouts.app')
@section('title', 'Record deposit')
@section('subtitle', 'Confirm a payment received through '.$s['payment_method'].' '.$s['paybill_number'].'.')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('payments.store') }}" id="depositForm">
                    @csrf
                    @include('partials.submission')

                    <div class="mb-3">
                        <label class="form-label" for="memberSearch">1. Member</label>
                        <input type="text" class="form-control" id="memberSearch" placeholder="Search by National ID, name or member number..." autocomplete="off"
                               value="{{ $member ? $member->full_name.' - ID '.$member->national_id : '' }}">
                        <input type="hidden" name="member_id" id="member_id" value="{{ old('member_id', $member?->id) }}">
                        <div class="list-group mt-1" id="memberResults"></div>
                        <div class="form-text" id="memberBalance">{{ $member ? 'Current wallet balance: '.money($member->balance) : '' }}</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="amount">2. Amount received ({{ $s['currency'] }})</label>
                            <input type="number" step="0.01" min="1" name="amount" id="amount" class="form-control" value="{{ old('amount') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="reference">3. Transaction code / reference</label>
                            <input type="text" name="reference" id="reference" class="form-control text-uppercase" value="{{ old('reference') }}" required maxlength="60" placeholder="e.g. SGH7K2L9QP">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="payment_date">4. Payment date</label>
                            <input type="date" name="payment_date" id="payment_date" class="form-control" value="{{ old('payment_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="transaction_cost">5. Transaction cost <span class="text-muted small">(optional)</span></label>
                            <input type="number" step="0.01" min="0" name="transaction_cost" id="transaction_cost" class="form-control" value="{{ old('transaction_cost') }}">
                            <div class="form-text">Charge paid on the transfer. Recorded for reference only, not added to the wallet.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="remarks">6. Remarks</label>
                            <input type="text" name="remarks" id="remarks" class="form-control" value="{{ old('remarks') }}" maxlength="250">
                        </div>
                    </div>
                    <button class="btn btn-primary mt-4">Save deposit</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body small">
                <p class="fw-semibold mb-2">What happens when you save</p>
                <ul class="mb-0 ps-3">
                    <li>The member's wallet is credited and a receipt is created.</li>
                    @if(\App\Support\Settings::bool('auto_apply_deposits'))
                        <li>Outstanding invoices are settled automatically from the wallet, oldest first.</li>
                    @endif
                    <li>The member is notified (dashboard and SMS).</li>
                    <li>Each transaction code can only be recorded once.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var $search = $('#memberSearch'), $results = $('#memberResults'), $id = $('#member_id'), timer;
    $search.on('input', function () {
        clearTimeout(timer);
        $id.val(''); $('#memberBalance').text('');
        var q = this.value.trim();
        if (q.length < 2) { $results.empty(); return; }
        timer = setTimeout(function () {
            $.getJSON(@json(route('members.search')), { q: q }, function (rows) {
                $results.empty();
                rows.forEach(function (r) {
                    $('<button type="button" class="list-group-item list-group-item-action">')
                        .text(r.text + ' · ' + r.balance)
                        .on('click', function () {
                            $search.val(r.text); $id.val(r.id); $results.empty();
                            $('#memberBalance').text('Current wallet balance: ' + r.balance);
                            $('#amount').focus();
                        }).appendTo($results);
                });
                if (!rows.length) $results.html('<div class="list-group-item text-muted">No members found.</div>');
            });
        }, 250);
    });
    $('#depositForm').on('submit', function (e) {
        if (!$id.val()) { e.preventDefault(); alert('Please select the member from the search results.'); }
    });
})();
</script>
@endpush
