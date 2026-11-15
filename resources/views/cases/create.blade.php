@extends('layouts.app')
@section('title', 'New welfare case')
@section('subtitle', 'Record a bereavement reported by a member. Invoices are generated for all active members.')

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('cases.store') }}" id="caseForm">
                    @csrf
                    @include('partials.submission')

                    <div class="mb-3">
                        <label class="form-label" for="memberSearch">1. Affected member</label>
                        <input type="text" class="form-control" id="memberSearch" placeholder="Type a name, National ID or member number..." autocomplete="off" value="{{ $member ? $member->full_name.' - ID '.$member->national_id : '' }}">
                        <input type="hidden" name="member_id" id="member_id" value="{{ old('member_id', $member?->id) }}">
                        <div class="list-group mt-1" id="memberResults"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="deceased">2. Who passed on?</label>
                        <select name="deceased" id="deceased" class="form-select" required>
                            <option value="">Select the member first</option>
                        </select>
                        <div class="form-text">Only the member's registered beneficiaries can be selected.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="bereavement_date">3. Date of bereavement</label>
                            <input type="date" name="bereavement_date" id="bereavement_date" class="form-control" value="{{ old('bereavement_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contribution per member</label>
                            <div class="form-control bg-light fw-semibold" id="amountPreview">-</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="remarks">4. Remarks</label>
                        <textarea name="remarks" id="remarks" rows="3" class="form-control" maxlength="1000">{{ old('remarks') }}</textarea>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="confirm" id="confirm" value="1" required>
                        <label class="form-check-label" for="confirm">5. I confirm these details are correct. Contribution invoices will be raised for every active member and deducted from wallets where funds are available.</label>
                    </div>
                    <button class="btn btn-primary">Create case and raise invoices</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Contribution rates</div>
            <table class="table mb-0">
                @foreach(\App\Models\WelfareCase::CATEGORIES as $cat => $label)
                    <tr><td>{{ $label }}</td><td class="text-end amount">{{ money($amounts[$cat]) }}</td></tr>
                @endforeach
            </table>
            <div class="card-body small text-muted">Rates are set in System Settings &rsaquo; Financial.</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var amounts = @json($amounts);
    var currency = @json($s['currency']);
    var oldDeceased = @json(old('deceased'));
    var $search = $('#memberSearch'), $results = $('#memberResults'), $id = $('#member_id'), $dec = $('#deceased');
    var timer;

    function fmt(n) { return currency + ' ' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 }); }

    function loadBeneficiaries(memberId) {
        $dec.html('<option value="">Loading...</option>');
        $.getJSON(@json(url('members')) + '/' + memberId + '/beneficiaries.json', function (list) {
            var html = '<option value="">Select...</option><option value="self" data-cat="self">The member themself</option>';
            list.forEach(function (b) {
                html += '<option value="' + b.id + '" data-cat="' + b.category + '">' + $('<div>').text(b.name).html() + ' (' + b.label + ')</option>';
            });
            $dec.html(html);
            if (oldDeceased) { $dec.val(oldDeceased); oldDeceased = null; }
            $dec.trigger('change');
        });
    }

    $search.on('input', function () {
        clearTimeout(timer);
        $id.val('');
        var q = this.value.trim();
        if (q.length < 2) { $results.empty(); return; }
        timer = setTimeout(function () {
            $.getJSON(@json(route('members.search')), { q: q }, function (rows) {
                $results.empty();
                rows.forEach(function (r) {
                    var disabled = r.status !== 'active';
                    $('<button type="button" class="list-group-item list-group-item-action">')
                        .text(r.text + (disabled ? ' [' + r.status + ']' : ''))
                        .prop('disabled', disabled)
                        .on('click', function () {
                            $search.val(r.text); $id.val(r.id); $results.empty(); loadBeneficiaries(r.id);
                        }).appendTo($results);
                });
                if (!rows.length) $results.html('<div class="list-group-item text-muted">No members found.</div>');
            });
        }, 250);
    });

    $dec.on('change', function () {
        var cat = $(this).find(':selected').data('cat');
        $('#amountPreview').text(cat ? fmt(amounts[cat]) : '-');
    });

    $('#caseForm').on('submit', function (e) {
        if (!$id.val()) { e.preventDefault(); alert('Please select the affected member from the search results.'); }
    });

    if ($id.val()) loadBeneficiaries($id.val());
})();
</script>
@endpush
