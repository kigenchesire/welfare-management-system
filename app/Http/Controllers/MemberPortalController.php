<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\Payment;
use App\Models\WelfareCase;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Self-service pages for members. Every query is scoped to the signed-in
 * member, so changing an ID in the URL can never expose another member's data.
 */
class MemberPortalController extends Controller
{
    private function member(Request $request): Member
    {
        $member = $request->user()->member;
        abort_unless($member, 403, 'No member profile is linked to this account.');

        return $member;
    }

    public function dashboard(Request $request)
    {
        $member = $this->member($request)->load('wallet');

        return view('member.dashboard', [
            'member' => $member,
            'outstanding' => $member->outstandingAmount(),
            'openInvoices' => $member->invoices()->open()->latest('invoice_date')->limit(5)->get(),
            'payments' => $member->payments()->latest('payment_date')->limit(5)->get(),
            'cases' => WelfareCase::with('member')->where('status', '!=', 'cancelled')->latest()->limit(5)->get(),
        ]);
    }

    public function profile(Request $request)
    {
        return view('member.profile', ['member' => $this->member($request)->load('wallet')]);
    }

    public function beneficiaries(Request $request)
    {
        $member = $this->member($request);

        return view('member.beneficiaries', ['member' => $member, 'beneficiaries' => $member->beneficiaries()->orderBy('category')->get()]);
    }

    public function wallet(Request $request)
    {
        $member = $this->member($request)->load('wallet');

        return view('member.wallet', [
            'member' => $member,
            'outstanding' => $member->outstandingAmount(),
            'transactions' => $member->walletTransactions()->latest('transaction_date')->latest('id')->paginate(25),
        ]);
    }

    public function invoices(Request $request)
    {
        $member = $this->member($request);

        return view('member.invoices', [
            'member' => $member,
            'invoices' => $member->invoices()->with('welfareCase')->latest('invoice_date')->latest('id')->get(),
        ]);
    }

    public function invoice(Request $request, int $invoice)
    {
        $invoice = $this->member($request)->invoices()->with(['welfareCase', 'transactions'])->findOrFail($invoice);

        return view('invoices.show', ['invoice' => $invoice, 'portal' => true]);
    }

    public function cases(Request $request)
    {
        $member = $this->member($request);
        $myInvoices = $member->invoices()->whereNotNull('welfare_case_id')->get()->keyBy('welfare_case_id');

        return view('member.cases', [
            'cases' => WelfareCase::with('member')->where('status', '!=', 'cancelled')->latest('bereavement_date')->get(),
            'myInvoices' => $myInvoices,
        ]);
    }

    public function payments(Request $request)
    {
        return view('member.payments', ['payments' => $this->member($request)->payments()->latest('payment_date')->get()]);
    }

    public function receipt(Request $request, int $payment)
    {
        $payment = $this->member($request)->payments()->findOrFail($payment);
        if ($request->boolean('pdf')) {
            return Pdf::loadView('payments.receipt-pdf', compact('payment'))->download($payment->receipt_no.'.pdf');
        }

        return view('payments.receipt', ['payment' => $payment, 'portal' => true]);
    }
}
