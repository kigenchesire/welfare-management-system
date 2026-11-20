<?php

namespace App\Services;

use App\Models\FinancialAdjustment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Financial figures. Member wallet money is a LIABILITY (it belongs to members
 * until used); only contributions and invoice payments actually deducted are
 * treated as collected welfare income.
 */
class FinanceService
{
    public function summary(?string $from = null, ?string $to = null): array
    {
        $range = fn ($q, $col) => $q->when($from, fn ($q) => $q->whereDate($col, '>=', $from))->when($to, fn ($q) => $q->whereDate($col, '<=', $to));

        $txSum = function (string $type, string $direction, ?string $invoiceType = null) use ($range) {
            $q = WalletTransaction::query()->where('wallet_transactions.type', $type)->where('direction', $direction);
            $range($q, 'wallet_transactions.transaction_date');
            if ($invoiceType) {
                $q->join('invoices', 'invoices.id', '=', 'wallet_transactions.invoice_id')->where('invoices.type', $invoiceType);
            }

            return (float) $q->sum('wallet_transactions.amount');
        };

        $deposits = (float) $range(Payment::where('status', 'confirmed'), 'payment_date')->sum('amount');
        $reversed = (float) $range(Payment::where('status', 'reversed'), 'payment_date')->sum('amount');

        $welfareGross = $txSum('contribution', 'debit');
        $welfareRefunds = $txSum('refund', 'credit', 'welfare');
        $customGross = $txSum('invoice_payment', 'debit');
        $customRefunds = $txSum('refund', 'credit', 'custom');

        $openInvoices = fn (string $type) => (float) $range(Invoice::open()->where('type', $type), 'invoice_date')
            ->sum(DB::raw('amount - amount_paid'));

        $customInvoiced = (float) $range(Invoice::where('type', 'custom')->where('status', '!=', 'cancelled'), 'invoice_date')->sum('amount');

        // Case funds (all dates): how welfare payments were shared out and paid out.
        $caseIds = \App\Models\WelfareCase::where('status', '!=', 'cancelled')->pluck('id')->all();
        $funds = collect(\App\Models\WelfareCase::fundsFor($caseIds));
        $office = app(OfficeService::class)->totals();

        return [
            'fund_welfare' => round($funds->sum('welfare'), 2),
            'fund_transport' => round($funds->sum('transport'), 2),
            'fund_office' => round($funds->sum('office'), 2),
            'welfare_disbursed' => round($funds->sum('welfare_disbursed'), 2),
            'transport_disbursed' => round($funds->sum('transport_disbursed'), 2),
            'welfare_held' => round($funds->sum('welfare_available'), 2),
            'transport_held' => round($funds->sum('transport_available'), 2),
            'office_balance' => $office['balance'],
            'office_topups' => $office['topups'],
            'office_expenses' => $office['expenses'],
            'wallet_balances' => (float) Wallet::sum('balance'),
            'members_in_credit' => Wallet::where('balance', '>', 0)->count(),
            'members_in_debit' => Wallet::where('balance', '<', 0)->count(),
            'deposits' => $deposits,
            'deposits_reversed' => $reversed,
            'deposit_count' => $range(Payment::where('status', 'confirmed'), 'payment_date')->count(),
            'welfare_collected' => round($welfareGross - $welfareRefunds, 2),
            'welfare_outstanding' => $openInvoices('welfare'),
            'custom_invoiced' => $customInvoiced,
            'custom_collected' => round($customGross - $customRefunds, 2),
            'custom_outstanding' => $openInvoices('custom'),
            'refunds' => $txSum('refund', 'credit'),
            'adjustments_credit' => (float) $range(FinancialAdjustment::where('direction', 'credit'), 'created_at')->sum('amount'),
            'adjustments_debit' => (float) $range(FinancialAdjustment::where('direction', 'debit'), 'created_at')->sum('amount'),
            'total_collected' => round($welfareGross - $welfareRefunds + $customGross - $customRefunds, 2),
            'total_outstanding' => $openInvoices('welfare') + $openInvoices('custom'),
        ];
    }

    /** Deposits vs contributions per month for the last 12 months (dashboard chart). */
    public function monthlyTrend(): array
    {
        $start = now()->subMonths(11)->startOfMonth();
        $labels = [];
        $deposits = [];
        $contributions = [];

        $dep = Payment::where('status', 'confirmed')->where('payment_date', '>=', $start)
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') m, SUM(amount) t")->groupBy('m')->pluck('t', 'm');
        $con = WalletTransaction::whereIn('type', ['contribution', 'invoice_payment'])->where('transaction_date', '>=', $start)
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') m, SUM(amount) t")->groupBy('m')->pluck('t', 'm');

        for ($i = 0; $i < 12; $i++) {
            $m = $start->copy()->addMonths($i);
            $labels[] = $m->format('M Y');
            $deposits[] = (float) ($dep[$m->format('Y-m')] ?? 0);
            $contributions[] = (float) ($con[$m->format('Y-m')] ?? 0);
        }

        return compact('labels', 'deposits', 'contributions');
    }
}
