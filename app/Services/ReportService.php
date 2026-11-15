<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Models\WelfareCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Every report is defined once here and rendered on screen, as PDF, Excel
 * and print from the same data.
 */
class ReportService
{
    /** key => [title, group (permission suffix), description] */
    public const REPORTS = [
        'member-register' => ['Member register', 'members', 'All registered members with contacts and wallet balance.'],
        'member-status' => ['Active and inactive members', 'members', 'Members grouped by membership status.'],
        'registrations' => ['Member registration report', 'members', 'Applications received, approved and rejected.'],
        'beneficiaries' => ['Beneficiary register', 'members', 'Registered beneficiaries for every member.'],
        'cases' => ['Welfare case report', 'welfare', 'All welfare cases with collection progress.'],
        'case-contributions' => ['Contribution report per case', 'welfare', 'Each member\'s contribution for a selected case.'],
        'payment-status' => ['Member payment status', 'welfare', 'Invoiced, paid and outstanding per member.'],
        'outstanding' => ['Outstanding contributions', 'welfare', 'Invoices that are unpaid or partially paid.'],
        'wallet-balances' => ['Member wallet balances', 'financial', 'Current wallet balance per member.'],
        'wallet-transactions' => ['Wallet transaction history', 'financial', 'Full wallet ledger.'],
        'deposits' => ['Deposit report', 'financial', 'Deposits recorded by the Treasurer.'],
        'custom-invoices' => ['Custom invoice report', 'financial', 'Non-welfare invoices (airtime, lunch, ...).'],
        'financial-summary' => ['Financial summary', 'financial', 'Totals for deposits, collections, liabilities and adjustments.'],
    ];

    public function build(string $key, Request $r): array
    {
        $from = $r->input('from');
        $to = $r->input('to');
        $range = fn ($q, $col) => $q->when($from, fn ($q) => $q->whereDate($col, '>=', $from))->when($to, fn ($q) => $q->whereDate($col, '<=', $to));

        $report = match ($key) {
            'member-register' => $this->memberRegister($range($this->members(), 'registration_date')->whereIn('status', ['active', 'suspended', 'inactive'])),
            'member-status' => $this->memberStatus($range($this->members(), 'registration_date')->when($r->input('status'), fn ($q, $s) => $q->where('status', $s))),
            'registrations' => $this->registrations($range(Member::with('approver'), 'registration_date')),
            'beneficiaries' => $this->beneficiaries($range(Beneficiary::with('member'), 'created_at')),
            'cases' => $this->cases($range(WelfareCase::with('member'), 'bereavement_date')->when($r->input('status'), fn ($q, $s) => $q->where('status', $s))),
            'case-contributions' => $this->caseContributions($r->input('case_id')),
            'payment-status' => $this->paymentStatus($from, $to),
            'outstanding' => $this->outstanding($range(Invoice::open()->with('member'), 'invoice_date')->when($r->input('type'), fn ($q, $t) => $q->where('type', $t))),
            'wallet-balances' => $this->walletBalances(),
            'wallet-transactions' => $this->walletTransactions($range(WalletTransaction::with(['member', 'recorder']), 'transaction_date')->when($r->input('type'), fn ($q, $t) => $q->where('type', $t))),
            'deposits' => $this->deposits($range(Payment::with(['member', 'recorder', 'welfareCase']), 'payment_date')),
            'custom-invoices' => $this->customInvoices($range(Invoice::with('member')->where('type', 'custom'), 'invoice_date')),
            'financial-summary' => $this->financialSummary($from, $to),
            default => abort(404),
        };

        // Free-text search across all cells (also applied to exports).
        if ($search = trim((string) $r->input('search'))) {
            $report['rows'] = array_values(array_filter($report['rows'], function ($row) use ($search) {
                return stripos(implode(' ', array_map('strval', $row)), $search) !== false;
            }));
        }

        $report['key'] = $key;
        $report['title'] = self::REPORTS[$key][0];
        $report['period'] = ($from || $to) ? trim(($from ? 'From '.$from : '').' '.($to ? 'to '.$to : '')) : 'All dates';
        $report['numeric'] ??= [];

        return $report;
    }

    private function members()
    {
        return Member::with('wallet')->orderBy('full_name');
    }

    private function memberRegister($q): array
    {
        $rows = $q->get()->map(fn ($m) => [
            $m->member_no, $m->full_name, $m->national_id, $m->phone, $m->email, $m->residential_address,
            $m->registration_date?->format('Y-m-d'), $m->status_label, number_format($m->balance, 2),
        ])->all();

        return ['columns' => ['Member No', 'Name', 'National ID', 'Phone', 'Email', 'Address', 'Registered', 'Status', 'Wallet'], 'rows' => $rows, 'numeric' => [8]];
    }

    private function memberStatus($q): array
    {
        $rows = $q->get()->map(fn ($m) => [
            $m->member_no, $m->full_name, $m->national_id, $m->phone, $m->status_label, $m->status_reason, $m->registration_date?->format('Y-m-d'),
        ])->all();

        return ['columns' => ['Member No', 'Name', 'National ID', 'Phone', 'Status', 'Reason', 'Registered'], 'rows' => $rows,
            'filters' => ['status' => Member::STATUSES]];
    }

    private function registrations($q): array
    {
        $rows = $q->orderByDesc('registration_date')->get()->map(fn ($m) => [
            $m->registration_date?->format('Y-m-d'), $m->full_name, $m->national_id, $m->phone, ucfirst($m->source), $m->status_label,
            $m->approved_at?->format('Y-m-d') ?? $m->rejected_at?->format('Y-m-d'), $m->approver?->name ?? $m->rejection_reason,
        ])->all();

        return ['columns' => ['Applied', 'Name', 'National ID', 'Phone', 'Source', 'Status', 'Decision date', 'Approved by / reason'], 'rows' => $rows];
    }

    private function beneficiaries($q): array
    {
        $rows = $q->get()->sortBy(fn ($b) => $b->member->full_name)->map(fn ($b) => [
            $b->member->member_no, $b->member->full_name, $b->name, $b->category_label, $b->is_deceased ? 'Deceased' : 'Living',
        ])->values()->all();

        return ['columns' => ['Member No', 'Member', 'Beneficiary', 'Category', 'Status'], 'rows' => $rows];
    }

    private function cases($q): array
    {
        $rows = $q->orderByDesc('bereavement_date')->get()->map(function ($c) {
            $s = $c->summary();

            return [
                $c->case_no, $c->bereavement_date->format('Y-m-d'), $c->member->full_name, $c->deceased_name, $c->category_label,
                number_format((float) $c->contribution_amount, 2), $s['invoices'], number_format($s['expected'], 2),
                number_format($s['collected'], 2), number_format($s['outstanding'], 2), $c->status_label,
            ];
        })->all();

        return ['columns' => ['Case No', 'Date', 'Member', 'Deceased', 'Category', 'Rate', 'Invoiced', 'Expected', 'Collected', 'Outstanding', 'Status'],
            'rows' => $rows, 'numeric' => [5, 7, 8, 9], 'filters' => ['status' => WelfareCase::STATUSES]];
    }

    private function caseContributions($caseId): array
    {
        $cases = WelfareCase::orderByDesc('id')->get()->mapWithKeys(fn ($c) => [$c->id => $c->case_no.' - '.$c->deceased_name])->all();
        $rows = [];
        $subtitle = 'Select a case';
        if ($caseId && $case = WelfareCase::find($caseId)) {
            $subtitle = $case->case_no.' - '.$case->deceased_name.' ('.$case->category_label.')';
            $rows = $case->invoices()->with('member')->get()->sortBy(fn ($i) => $i->member->full_name)->map(fn ($i) => [
                $i->member->member_no, $i->member->full_name, $i->member->phone, $i->invoice_no, number_format((float) $i->amount, 2),
                number_format((float) $i->amount_paid, 2), number_format($i->outstanding, 2), $i->status_label,
            ])->values()->all();
        }

        return ['columns' => ['Member No', 'Member', 'Phone', 'Invoice', 'Amount', 'Paid', 'Outstanding', 'Status'],
            'rows' => $rows, 'numeric' => [4, 5, 6], 'cases' => $cases, 'subtitle' => $subtitle];
    }

    private function paymentStatus($from, $to): array
    {
        $rows = Member::whereIn('status', ['active', 'suspended', 'inactive'])->orderBy('full_name')
            ->withSum(['invoices as invoiced' => fn ($q) => $q->where('status', '!=', 'cancelled')
                ->when($from, fn ($q) => $q->whereDate('invoice_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('invoice_date', '<=', $to))], 'amount')
            ->withSum(['invoices as paid' => fn ($q) => $q->where('status', '!=', 'cancelled')
                ->when($from, fn ($q) => $q->whereDate('invoice_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('invoice_date', '<=', $to))], 'amount_paid')
            ->with('wallet')->get()->map(function ($m) {
                $out = (float) $m->invoiced - (float) $m->paid;

                return [$m->member_no, $m->full_name, $m->phone, number_format((float) $m->invoiced, 2), number_format((float) $m->paid, 2),
                    number_format($out, 2), number_format($m->balance, 2), $out > 0 ? 'Owing' : 'Cleared'];
            })->all();

        return ['columns' => ['Member No', 'Member', 'Phone', 'Invoiced', 'Paid', 'Outstanding', 'Wallet', 'Status'], 'rows' => $rows, 'numeric' => [3, 4, 5, 6]];
    }

    private function outstanding($q): array
    {
        $rows = $q->orderBy('invoice_date')->get()->map(fn ($i) => [
            $i->invoice_no, $i->invoice_date->format('Y-m-d'), $i->member->member_no, $i->member->full_name, $i->member->phone,
            $i->title, number_format((float) $i->amount, 2), number_format((float) $i->amount_paid, 2), number_format($i->outstanding, 2),
        ])->all();

        return ['columns' => ['Invoice', 'Date', 'Member No', 'Member', 'Phone', 'Description', 'Amount', 'Paid', 'Outstanding'],
            'rows' => $rows, 'numeric' => [6, 7, 8], 'filters' => ['type' => ['welfare' => 'Welfare', 'custom' => 'Custom']]];
    }

    private function walletBalances(): array
    {
        $rows = Member::with('wallet')->whereNotNull('member_no')->orderBy('full_name')->get()->map(fn ($m) => [
            $m->member_no, $m->full_name, $m->phone, $m->status_label, number_format($m->balance, 2), number_format($m->outstandingAmount(), 2),
        ])->all();

        return ['columns' => ['Member No', 'Member', 'Phone', 'Status', 'Wallet balance', 'Outstanding'], 'rows' => $rows, 'numeric' => [4, 5]];
    }

    private function walletTransactions($q): array
    {
        $rows = $q->orderByDesc('transaction_date')->orderByDesc('id')->limit(5000)->get()->map(fn ($t) => [
            $t->transaction_date->format('Y-m-d H:i'), $t->reference, $t->member->full_name, $t->type_label,
            ($t->direction === 'credit' ? '+' : '-').number_format((float) $t->amount, 2),
            number_format((float) $t->balance_before, 2), number_format((float) $t->balance_after, 2), $t->description, $t->recorder?->name,
        ])->all();

        return ['columns' => ['Date', 'Reference', 'Member', 'Type', 'Amount', 'Before', 'After', 'Description', 'Recorded by'],
            'rows' => $rows, 'numeric' => [4, 5, 6], 'filters' => ['type' => WalletTransaction::TYPES]];
    }

    private function deposits($q): array
    {
        $rows = $q->orderByDesc('payment_date')->get()->map(fn ($p) => [
            $p->payment_date->format('Y-m-d'), $p->receipt_no, $p->reference, $p->member->member_no, $p->member->full_name,
            number_format((float) $p->amount, 2), number_format((float) $p->transaction_cost, 2), $p->welfareCase?->case_no ?? '-', ucfirst($p->status), $p->recorder?->name,
        ])->all();

        return ['columns' => ['Date', 'Receipt', 'Reference', 'Member No', 'Member', 'Amount', 'Txn cost', 'Case', 'Status', 'Recorded by'], 'rows' => $rows, 'numeric' => [5, 6]];
    }

    private function customInvoices($q): array
    {
        $rows = $q->orderByDesc('invoice_date')->get()->map(fn ($i) => [
            $i->invoice_no, $i->invoice_date->format('Y-m-d'), $i->member->full_name, $i->category_label, $i->title,
            number_format((float) $i->amount, 2), number_format((float) $i->amount_paid, 2), number_format($i->outstanding, 2), $i->status_label,
        ])->all();

        return ['columns' => ['Invoice', 'Date', 'Member', 'Category', 'Title', 'Amount', 'Paid', 'Outstanding', 'Status'], 'rows' => $rows, 'numeric' => [5, 6, 7]];
    }

    private function financialSummary($from, $to): array
    {
        $s = app(FinanceService::class)->summary($from, $to);
        $rows = [
            ['Member wallet balances (liability, as at today)', number_format($s['wallet_balances'], 2)],
            ['Deposits received', number_format($s['deposits'], 2)],
            ['Deposits reversed', number_format($s['deposits_reversed'], 2)],
            ['Welfare contributions collected (net of refunds)', number_format($s['welfare_collected'], 2)],
            ['Welfare contributions outstanding', number_format($s['welfare_outstanding'], 2)],
            ['Custom invoices raised', number_format($s['custom_invoiced'], 2)],
            ['Custom invoices collected (net of refunds)', number_format($s['custom_collected'], 2)],
            ['Custom invoices outstanding', number_format($s['custom_outstanding'], 2)],
            ['Total payments collected from wallets', number_format($s['total_collected'], 2)],
            ['Total refunds', number_format($s['refunds'], 2)],
            ['Adjustments (credit)', number_format($s['adjustments_credit'], 2)],
            ['Adjustments (debit)', number_format($s['adjustments_debit'], 2)],
        ];

        return ['columns' => ['Item', 'Amount ('.setting('currency', 'KES').')'], 'rows' => $rows, 'numeric' => [1]];
    }
}
