<?php

namespace App\Services;

use App\Models\FinancialAdjustment;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WelfareCase;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * All wallet balance changes go through this class. Each change:
 *  - runs inside a database transaction,
 *  - locks the wallet row (SELECT ... FOR UPDATE) so concurrent requests can't race,
 *  - writes one immutable WalletTransaction with balance before/after.
 */
class WalletService
{
    public function walletFor(Member $member): Wallet
    {
        return Wallet::firstOrCreate(['member_id' => $member->id], ['balance' => 0]);
    }

    public function credit(Member $member, float $amount, string $type, string $description, array $links = []): WalletTransaction
    {
        return $this->post($member, round($amount, 2), 'credit', $type, $description, $links, false);
    }

    public function debit(Member $member, float $amount, string $type, string $description, array $links = [], bool $allowNegative = false): WalletTransaction
    {
        return $this->post($member, round($amount, 2), 'debit', $type, $description, $links, $allowNegative);
    }

    private function post(Member $member, float $amount, string $direction, string $type, string $description, array $links, bool $allowNegative): WalletTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException('Transaction amount must be greater than zero.');
        }

        return DB::transaction(function () use ($member, $amount, $direction, $type, $description, $links, $allowNegative) {
            $this->walletFor($member);
            $wallet = Wallet::where('member_id', $member->id)->lockForUpdate()->firstOrFail();

            $before = round((float) $wallet->balance, 2);
            $after = round($direction === 'credit' ? $before + $amount : $before - $amount, 2);

            if ($after < 0 && ! $allowNegative) {
                throw new RuntimeException('Insufficient wallet balance. Available: '.money($before).'.');
            }

            $wallet->balance = $after;
            $wallet->save();

            $tx = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'member_id' => $member->id,
                'reference' => 'TX'.now()->format('ymd').strtoupper(Str::random(8)),
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => mb_substr($description, 0, 250),
                'invoice_id' => $links['invoice_id'] ?? null,
                'payment_id' => $links['payment_id'] ?? null,
                'adjustment_id' => $links['adjustment_id'] ?? null,
                'recorded_by' => auth()->id(),
                'transaction_date' => $links['date'] ?? now(),
            ]);

            AuditLogger::log('wallet.'.$type, $tx, ['balance' => $before], [
                'direction' => $direction, 'amount' => $amount, 'balance' => $after, 'reference' => $tx->reference,
            ], $description);

            if ($direction === 'debit') {
                $this->checkLowBalance($member, $before, $after);
            }

            return $tx;
        });
    }

    /**
     * Pay as much of an invoice as the wallet allows. Returns the transaction, or null
     * when there is nothing to pay or no money available.
     */
    public function applyToInvoice(Invoice $invoice): ?WalletTransaction
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::lockForUpdate()->find($invoice->id);
            if (! $invoice || ! in_array($invoice->status, ['unpaid', 'partially_paid'], true)) {
                return null;
            }
            $member = $invoice->member;
            $this->walletFor($member);
            $balance = (float) Wallet::where('member_id', $member->id)->lockForUpdate()->value('balance');
            $pay = round(min($balance, $invoice->outstanding), 2);
            if ($pay <= 0) {
                return null;
            }

            $type = $invoice->type === 'welfare' ? 'contribution' : 'invoice_payment';
            $tx = $this->debit($member, $pay, $type, 'Payment for '.$invoice->invoice_no.' - '.$invoice->title, ['invoice_id' => $invoice->id]);

            $invoice->amount_paid = round((float) $invoice->amount_paid + $pay, 2);
            $invoice->refreshStatus();
            $invoice->save();

            return $tx;
        });
    }

    /**
     * Use the member's wallet balance to settle open invoices, oldest first.
     *
     * @return array<int> ids of welfare cases whose collections changed
     */
    public function settleOutstanding(Member $member): array
    {
        $caseIds = [];
        $invoices = $member->invoices()->open()->orderBy('invoice_date')->orderBy('id')->get();
        foreach ($invoices as $invoice) {
            if ($this->applyToInvoice($invoice) && $invoice->welfare_case_id) {
                $caseIds[] = $invoice->welfare_case_id;
            }
            if ((float) $this->walletFor($member)->fresh()->balance <= 0) {
                break;
            }
        }

        return array_values(array_unique($caseIds));
    }

    /** Return money already paid on an invoice to the wallet (used when cancelling). */
    public function refundInvoice(Invoice $invoice, string $reason): ?WalletTransaction
    {
        $paid = round((float) $invoice->amount_paid, 2);
        if ($paid <= 0) {
            return null;
        }

        return $this->credit($invoice->member, $paid, 'refund', 'Refund for cancelled '.$invoice->invoice_no.': '.$reason, ['invoice_id' => $invoice->id]);
    }

    public function adjust(Member $member, string $direction, float $amount, string $reason, bool $authorizeNegative): FinancialAdjustment
    {
        return DB::transaction(function () use ($member, $direction, $amount, $reason, $authorizeNegative) {
            $adj = FinancialAdjustment::create([
                'reference' => 'ADJ'.strtoupper(Str::random(10)),
                'member_id' => $member->id,
                'direction' => $direction,
                'amount' => round($amount, 2),
                'reason' => $reason,
                'negative_balance_authorized' => $authorizeNegative,
                'created_by' => auth()->id(),
            ]);
            $adj->update(['reference' => 'ADJ'.str_pad((string) $adj->id, 6, '0', STR_PAD_LEFT)]);

            $desc = 'Adjustment '.$adj->reference.': '.$reason;
            $direction === 'credit'
                ? $this->credit($member, $amount, 'adjustment', $desc, ['adjustment_id' => $adj->id])
                : $this->debit($member, $amount, 'adjustment', $desc, ['adjustment_id' => $adj->id], $authorizeNegative);

            AuditLogger::log('finance.adjustment', $adj, null, $adj->only(['reference', 'direction', 'amount', 'reason', 'negative_balance_authorized']));

            return $adj;
        });
    }

    private function checkLowBalance(Member $member, float $before, float $after): void
    {
        $threshold = Settings::money('low_balance_threshold');
        if ($threshold > 0 && $before >= $threshold && $after < $threshold) {
            NotificationService::toMember($member, 'low_balance', 'Low wallet balance',
                'Your wallet balance is '.money($after).'. Please top up to cover future contributions.', route('member.wallet'));
        }
    }

    /** Refresh the collection status of welfare cases after payments. */
    public static function refreshCases(array $caseIds): void
    {
        WelfareCase::whereIn('id', $caseIds)->get()->each(fn ($c) => app(WelfareCaseService::class)->refreshStatus($c));
    }
}
