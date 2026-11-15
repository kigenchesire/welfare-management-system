<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Member;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices)
    {
    }

    public function index(Request $request)
    {
        $type = $request->input('type');
        $invoices = Invoice::with(['member', 'welfareCase'])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($request->input('status'), fn ($q, $s) => $s === 'open' ? $q->open() : $q->where('status', $s))
            ->latest('invoice_date')->latest('id')->limit(3000)->get();

        return view('invoices.index', compact('invoices', 'type'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['member', 'welfareCase', 'transactions']);

        return view('invoices.show', ['invoice' => $invoice, 'portal' => false]);
    }

    public function create()
    {
        return view('invoices.create', [
            'members' => Member::active()->orderBy('full_name')->get(),
            'categories' => Invoice::CUSTOM_CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(Invoice::CUSTOM_CATEGORIES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'due_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:250'],
            'recipients' => ['required', Rule::in(['all', 'selected'])],
            'member_ids' => ['required_if:recipients,selected', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        $ids = $data['recipients'] === 'all'
            ? Member::active()->pluck('id')->all()
            : $data['member_ids'];

        $count = $this->invoices->createCustom($ids, $data, $request->boolean('deduct'), $request->boolean('send_sms'));

        return redirect()->route('invoices.index', ['type' => 'custom'])->with('success', $count.' invoice(s) created.');
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $this->invoices->cancel($invoice, $data['reason']);

        return back()->with('success', 'Invoice cancelled. Any amount paid was refunded to the member\'s wallet.');
    }

    public function payFromWallet(Invoice $invoice)
    {
        $paid = $this->invoices->payFromWallet($invoice);

        return back()->with($paid ? 'success' : 'error', $paid ? 'Payment applied from the member\'s wallet.' : 'The member has no wallet balance available.');
    }
}
