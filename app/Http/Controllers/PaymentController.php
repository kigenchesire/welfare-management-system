<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Payment;
use App\Services\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments)
    {
    }

    public function index(Request $request)
    {
        $from = $request->input('from', now()->subDays(90)->toDateString());
        $to = $request->input('to', now()->toDateString());
        $payments = Payment::with(['member', 'recorder'])
            ->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)
            ->latest('payment_date')->latest('id')->get();

        return view('payments.index', compact('payments', 'from', 'to'));
    }

    public function create(Request $request)
    {
        $member = $request->input('member_id') ? Member::with('wallet')->find($request->input('member_id')) : null;

        return view('payments.create', compact('member'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'reference' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'transaction_cost' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'remarks' => ['nullable', 'string', 'max:250'],
        ], ['reference.regex' => 'The reference may only contain letters, numbers, dashes and slashes.']);

        $member = Member::findOrFail($data['member_id']);
        abort_if(in_array($member->status, ['pending', 'rejected'], true), 422, 'Deposits can only be recorded for approved members.');

        $payment = $this->payments->recordDeposit($member, $data);

        return redirect()->route('payments.show', $payment)
            ->with('success', 'Deposit recorded. New wallet balance: '.money($member->wallet()->value('balance')).'.');
    }

    public function show(Request $request, Payment $payment)
    {
        if ($request->boolean('pdf')) {
            return Pdf::loadView('payments.receipt-pdf', compact('payment'))->download($payment->receipt_no.'.pdf');
        }

        return view('payments.receipt', ['payment' => $payment->load(['member.wallet', 'recorder', 'transactions.invoice']), 'portal' => false]);
    }

    public function reverse(Request $request, Payment $payment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $this->payments->reverse($payment, $data['reason'], $request->boolean('authorize_negative'));

        return back()->with('success', 'Payment reversed. A reversal entry was posted to the wallet ledger.');
    }
}
