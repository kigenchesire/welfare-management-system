<?php

namespace App\Http\Controllers;

use App\Models\OfficeTransaction;
use App\Services\AuditLogger;
use App\Services\OfficeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfficeController extends Controller
{
    public function __construct(private OfficeService $office)
    {
    }

    public function index(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');

        return view('office.index', [
            'account' => $this->office->account(),
            'totals' => $this->office->totals(),
            'statement' => $this->office->statement($from, $to, $request->input('type') ?: null),
            'reversedIds' => OfficeTransaction::whereNotNull('reverses_id')->pluck('reverses_id')->flip(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function topup(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'party' => ['required', 'string', 'max:150'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'external_ref' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:250'],
        ], [], ['party' => 'source']);
        $data['description'] = ($data['description'] ?? null) ?: 'Top-up from '.$data['party'];
        $tx = $this->office->topup($data);

        return back()->with('success', 'Top-up '.$tx->reference.' of '.money($tx->amount).' added. Office balance: '.money($this->office->balance()).'.');
    }

    public function expense(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'description' => ['required', 'string', 'max:250'],
            'category' => ['nullable', 'string', 'max:60'],
            'party' => ['nullable', 'string', 'max:150'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'external_ref' => ['nullable', 'string', 'max:60'],
        ], [], ['party' => 'paid to']);
        $tx = $this->office->expense($data);

        return back()->with('success', 'Expense '.$tx->reference.' of '.money($tx->amount).' deducted. Office balance: '.money($this->office->balance()).'.');
    }

    public function reverse(Request $request, OfficeTransaction $transaction)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $tx = $this->office->reverse($transaction, $data['reason']);

        return back()->with('success', 'Entry '.$transaction->reference.' reversed by '.$tx->reference.'.');
    }

    public function updateAccount(Request $request)
    {
        $account = $this->office->account();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'account_no' => ['required', 'string', 'max:40', Rule::unique('office_accounts', 'account_no')->ignore($account->id)],
        ]);
        $old = $account->only(['name', 'account_no']);
        $account->update($data);
        AuditLogger::log('office.account_updated', $account, $old, $data);

        return back()->with('success', 'Office account details saved.');
    }
}
