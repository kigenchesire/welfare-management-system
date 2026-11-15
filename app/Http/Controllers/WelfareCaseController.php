<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\WelfareCase;
use App\Services\PaymentService;
use App\Services\WelfareCaseService;
use App\Support\Settings;
use Illuminate\Http\Request;

class WelfareCaseController extends Controller
{
    public function __construct(private WelfareCaseService $cases)
    {
    }

    public function index(Request $request)
    {
        $cases = WelfareCase::with('member')
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('bereavement_date')->latest('id')->get();
        $summaries = $cases->mapWithKeys(fn ($c) => [$c->id => $c->summary()]);

        return view('cases.index', compact('cases', 'summaries'));
    }

    public function create(Request $request)
    {
        $amounts = collect(WelfareCase::CATEGORIES)->mapWithKeys(fn ($label, $cat) => [$cat => Settings::contributionFor($cat)]);
        $member = $request->input('member_id') ? Member::find($request->input('member_id')) : null;

        return view('cases.create', compact('amounts', 'member'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'deceased' => ['required', 'string'], // "self" or a beneficiary id
            'bereavement_date' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Please tick the confirmation box.']);

        $member = Member::findOrFail($data['member_id']);
        $beneficiaryId = $data['deceased'] === 'self' ? null : (int) $data['deceased'];
        $case = $this->cases->create($member, $beneficiaryId, $data['bereavement_date'], $data['remarks'] ?? null);

        return redirect()->route('cases.show', $case)
            ->with('success', 'Case '.$case->case_no.' created. '.$case->members_invoiced.' contribution invoices generated.');
    }

    public function show(WelfareCase $case)
    {
        $case->load(['member', 'beneficiary', 'creator']);

        return view('cases.show', [
            'case' => $case,
            'summary' => $case->summary(),
            'invoices' => $case->invoices()->with('member')->get()->sortBy(fn ($i) => $i->member->full_name),
        ]);
    }

    /** Re-run invoicing (e.g. after new members joined). Never duplicates existing invoices. */
    public function process(WelfareCase $case)
    {
        $result = $this->cases->process($case);

        return back()->with('success', $result['created'] ? $result['created'].' new invoice(s) generated.' : 'All eligible members already have an invoice for this case.');
    }

    public function close(WelfareCase $case)
    {
        $this->cases->close($case);

        return back()->with('success', 'Case closed.');
    }

    /** Record a Paybill deposit against this case: the member's invoice is paid first, fully or partly. */
    public function recordPayment(Request $request, WelfareCase $case, PaymentService $payments)
    {
        abort_if($case->status === 'cancelled', 422, 'Payments cannot be recorded on a cancelled case.');
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'reference' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'transaction_cost' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'remarks' => ['nullable', 'string', 'max:250'],
        ], ['reference.regex' => 'The reference may only contain letters, numbers, dashes and slashes.']);

        $member = Member::findOrFail($data['member_id']);
        $payment = $payments->recordDeposit($member, $data, $case);
        $invoice = $case->invoices()->where('member_id', $member->id)->first();

        return redirect()->route('cases.show', $case)->with('success',
            'Payment '.$payment->receipt_no.' of '.money($payment->amount).' recorded for '.$member->full_name.'. '
            .($invoice->outstanding > 0 ? 'Still outstanding on this case: '.money($invoice->outstanding).'.' : 'Their contribution for this case is fully paid.'));
    }

    public function remind(Request $request, WelfareCase $case)
    {
        $data = $request->validate(['invoice_ids' => ['nullable', 'array'], 'invoice_ids.*' => ['integer']]);
        $count = $this->cases->remindUnpaid($case, $data['invoice_ids'] ?? null);

        return back()->with($count ? 'success' : 'error', $count
            ? 'Reminder SMS queued for '.$count.' member(s), each with the amount they owe. See SMS Logs for delivery status.'
            : 'No members with an outstanding balance on this case.');
    }

    public function cancel(Request $request, WelfareCase $case)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $this->cases->cancel($case, $data['reason']);

        return back()->with('success', 'Case cancelled. All invoices were cancelled and any deductions refunded to member wallets.');
    }
}
