<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\WalletTransaction;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\SmsService;
use App\Services\WalletService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function index()
    {
        $members = Member::with('wallet')->whereNotNull('member_no')
            ->withSum(['invoices as outstanding' => fn ($q) => $q->open()], \DB::raw('amount - amount_paid'))
            ->orderBy('full_name')->get();

        return view('wallets.index', compact('members'));
    }

    public function show(Member $member)
    {
        $member->load('wallet');

        return view('wallets.show', [
            'member' => $member,
            'outstanding' => $member->outstandingAmount(),
            'transactions' => $member->walletTransactions()->with('recorder')->latest('transaction_date')->latest('id')->paginate(30),
        ]);
    }

    public function transactions(Request $request)
    {
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to = $request->input('to', now()->toDateString());
        $transactions = WalletTransaction::with(['member', 'recorder'])
            ->whereDate('transaction_date', '>=', $from)->whereDate('transaction_date', '<=', $to)
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->latest('transaction_date')->latest('id')->paginate(50)->withQueryString();

        return view('wallets.transactions', compact('transactions', 'from', 'to'));
    }

    /** Send reminders (dashboard + SMS) to active members whose wallet is below the configured threshold. */
    public function remindLowBalances(SmsService $sms)
    {
        $threshold = Settings::money('low_balance_threshold');
        $members = Member::active()->with('wallet')->get()->filter(fn ($m) => $m->balance < $threshold);

        $messages = [];
        foreach ($members as $m) {
            NotificationService::toMember($m, 'low_balance', 'Low wallet balance',
                'Your wallet balance is '.money($m->balance).'. Please top up via '.Settings::get('payment_method').' '.Settings::get('paybill_number').'.', route('member.wallet'));
            $messages[] = [$m->phone, SmsService::render('sms_tpl_low_balance', ['name' => $m->full_name, 'balance' => number_format($m->balance, 2)]), $m];
        }
        $sms->sendLater($messages, 'low_balance');
        AuditLogger::log('sms.low_balance_reminders', null, null, ['members' => $members->count(), 'threshold' => $threshold]);

        return back()->with('success', 'Reminders sent to '.$members->count().' member(s) with a balance below '.money($threshold).'.');
    }

    public function adjust(Request $request, Member $member, WalletService $wallets)
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'reason' => ['required', 'string', 'max:250'],
        ]);

        try {
            $wallets->adjust($member, $data['direction'], (float) $data['amount'], $data['reason'], $request->boolean('authorize_negative'));
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage().' Tick "authorise negative balance" to post it anyway.');
        }

        return back()->with('success', 'Adjustment posted.');
    }
}
