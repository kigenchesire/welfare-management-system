@extends('layouts.app')
@section('title', 'My wallet')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4">@include('partials.stat', ['label' => 'Current balance', 'value' => money($member->balance), 'class' => 'text-brand'])</div>
    <div class="col-md-4">@include('partials.stat', ['label' => 'Outstanding contributions', 'value' => money($outstanding), 'class' => $outstanding > 0 ? 'text-danger' : ''])</div>
    <div class="col-md-4">@include('partials.stat', ['label' => 'Top up via '.$s['payment_method'], 'value' => $s['paybill_number'], 'hint' => e($s['account_instructions'])])</div>
</div>

<div class="card">
    <div class="card-header">Wallet statement</div>
    @include('wallets._ledger', ['transactions' => $transactions, 'showMember' => false])
</div>
@endsection
