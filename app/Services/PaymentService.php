<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Payment;
use App\Models\WelfareCase;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private WalletService $wallets, private SmsService $sms)
    {
    }

    /**
     * Record a confirmed Paybill deposit, credit the wallet and optionally settle open invoices.
     * With a $case, the money goes to this member's invoice for that case first (fully or partly);
     * anything left over stays in the wallet, or settles other invoices if auto-apply is on.
     */
    public function recordDeposit(Member $member, array $data, ?WelfareCase $case = null): Payment
    {
        $reference = strtoupper(trim($data['reference']));
        if (Payment::where('reference', $reference)->exists()) {
            throw ValidationException::withMessages(['reference' => 'A payment with reference '.$reference.' has already been recorded.']);
        }

        $caseInvoice = null;
        if ($case) {
            $caseInvoice = $case->invoices()->where('member_id', $member->id)->first();
            if (! $caseInvoice) {
                throw ValidationException::withMessages(['member_id' => 'This member has no invoice for case '.$case->case_no.'.']);
            }
        }

        [$payment, $caseIds] = DB::transaction(function () use ($member, $data, $reference, $case, $caseInvoice) {
            $payment = Payment::create([
                'receipt_no' => 'TMP'.uniqid('', true),
                'member_id' => $member->id,
                'welfare_case_id' => $case?->id,
                'reference' => $reference,
                'amount' => round((float) $data['amount'], 2),
                'transaction_cost' => isset($data['transaction_cost']) && $data['transaction_cost'] !== '' ? round((float) $data['transaction_cost'], 2) : null,
                'payment_date' => $data['payment_date'],
                'method' => Settings::get('payment_method', 'Paybill'),
                'remarks' => $data['remarks'] ?? null,
                'status' => 'confirmed',
                'recorded_by' => auth()->id(),
            ]);
            $payment->update(['receipt_no' => 'RCT'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)]);

            $this->wallets->credit($member, (float) $payment->amount, 'deposit',
                'Deposit '.$payment->reference.' ('.$payment->method.')', ['payment_id' => $payment->id]);

            $caseIds = [];
            if ($caseInvoice && $this->wallets->applyToInvoice($caseInvoice)) {
                $caseIds[] = $case->id;
            }
            if (Settings::bool('auto_apply_deposits')) {
                $caseIds = array_values(array_unique(array_merge($caseIds, $this->wallets->settleOutstanding($member))));
            }
            WalletService::refreshCases($caseIds);

            AuditLogger::log('payment.recorded', $payment, null, $payment->only(['receipt_no', 'member_id', 'welfare_case_id', 'reference', 'amount', 'transaction_cost', 'payment_date']));

            return [$payment, $caseIds];
        });

        try {
            $balance = (float) $member->wallet()->value('balance');
            NotificationService::toMember($member, 'payment', 'Payment received',
                'We received '.money($payment->amount).' (Ref '.$payment->reference.'). Wallet balance: '.money($balance).'.', route('member.payments'));
            $this->sms->send($member->phone, SmsService::render('sms_tpl_payment', [
                'name' => $member->full_name,
                'amount' => number_format((float) $payment->amount, 2),
                'reference' => $payment->reference,
                'balance' => number_format($balance, 2),
            ]), 'payment', $member);
        } catch (\Throwable $e) {
            report($e);
        }

        return $payment;
    }

    /**
     * Reverse a payment recorded in error. The original rows stay untouched;
     * a reversal debit is posted to the wallet.
     */
    public function reverse(Payment $payment, string $reason, bool $authorizeNegative): void
    {
        DB::transaction(function () use ($payment, $reason, $authorizeNegative) {
            $payment = Payment::lockForUpdate()->find($payment->id);
            if ($payment->status === 'reversed') {
                throw ValidationException::withMessages(['reason' => 'This payment has already been reversed.']);
            }
            try {
                $this->wallets->debit($payment->member, (float) $payment->amount, 'reversal',
                    'Reversal of '.$payment->reference.': '.$reason, ['payment_id' => $payment->id], $authorizeNegative);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['reason' => $e->getMessage().' Tick "authorise negative balance" to reverse anyway.']);
            }
            $payment->update(['status' => 'reversed', 'reversed_by' => auth()->id(), 'reversed_at' => now(), 'reversal_reason' => $reason]);
            AuditLogger::log('payment.reversed', $payment, ['status' => 'confirmed'], ['status' => 'reversed', 'reason' => $reason]);
        });
    }
}
