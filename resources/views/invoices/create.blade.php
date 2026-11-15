@extends('layouts.app')
@section('title', 'New custom invoice')
@section('subtitle', 'Invoice members for airtime, accessories, lunch, activities or special contributions.')

@section('actions')
    <a href="{{ route('invoices.index', ['type' => 'custom']) }}" class="btn btn-outline-secondary">View custom invoices</a>
@endsection

@section('content')
<form method="POST" action="{{ route('invoices.store') }}">
    @csrf
    @include('partials.submission')
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="title">Invoice title</label>
                            <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" required maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="category">Category</label>
                            <select name="category" id="category" class="form-select">
                                @foreach($categories as $k => $label)
                                    <option value="{{ $k }}" @selected(old('category') === $k)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="2" maxlength="1000">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="amount">Amount per member ({{ $s['currency'] }})</label>
                            <input type="number" step="0.01" min="1" name="amount" id="amount" class="form-control" value="{{ old('amount') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="due_date">Due date</label>
                            <input type="date" name="due_date" id="due_date" class="form-control" value="{{ old('due_date') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="remarks">Remarks</label>
                            <input type="text" name="remarks" id="remarks" class="form-control" value="{{ old('remarks') }}" maxlength="250">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="deduct" id="deduct" value="1" @checked(old('deduct', true))>
                                <label class="form-check-label" for="deduct">Deduct from member wallets where funds are available</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_sms" id="send_sms" value="1" @checked(old('send_sms'))>
                                <label class="form-check-label" for="send_sms">Notify members by SMS</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Members</div>
                <div class="card-body">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="recipients" id="rAll" value="all" @checked(old('recipients', 'all') === 'all')>
                        <label class="form-check-label" for="rAll">All active members ({{ $members->count() }})</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="recipients" id="rSel" value="selected" @checked(old('recipients') === 'selected')>
                        <label class="form-check-label" for="rSel">Selected members only</label>
                    </div>
                    <input type="search" class="form-control form-control-sm mb-2" id="memberFilter" placeholder="Filter members...">
                    <div class="border rounded p-2" style="max-height: 320px; overflow-y: auto;" id="memberList">
                        @foreach($members as $m)
                            <div class="form-check member-item">
                                <input class="form-check-input" type="checkbox" name="member_ids[]" value="{{ $m->id }}" id="m{{ $m->id }}" @checked(in_array($m->id, old('member_ids', [])))>
                                <label class="form-check-label" for="m{{ $m->id }}">{{ $m->full_name }} <span class="small text-muted">{{ $m->member_no }}</span></label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <button class="btn btn-primary w-100 mt-3" data-confirm-text>Create invoices</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.getElementById('memberFilter').addEventListener('input', function () {
        var q = this.value.toLowerCase();
        document.querySelectorAll('#memberList .member-item').forEach(function (el) {
            el.style.display = el.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
        });
    });
    document.querySelectorAll('#memberList input').forEach(function (cb) {
        cb.addEventListener('change', function () { document.getElementById('rSel').checked = true; });
    });
</script>
@endpush
