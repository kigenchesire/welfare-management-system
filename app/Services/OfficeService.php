<?php

namespace App\Services;

use App\Models\OfficeAccount;
use App\Models\OfficeTransaction;
use App\Models\WalletTransaction;
use App\Models\WelfareCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The office expenditure account.
 *
 * Money comes in from:
 *  - the office share of every member's welfare-case payment (calculated from paid
 *    invoices, so it always matches them, including after refunds), and
 *  - top-ups from other sources.
 * Money goes out through expenses. The balance can never go below zero.
 */
class OfficeService
{
    public function account(): OfficeAccount
    {
        return OfficeAccount::query()->orderBy('id')->firstOr(fn () => OfficeAccount::create([
            'name' => 'Office Expenditure Account', 'account_no' => 'OFF-0001',
        ]));
    }

    /** Office share collected from all welfare cases (net of refunds on cancelled cases). */
    public function caseIncome(): float
    {
        $office = WelfareCase::splitSql('amount_paid')['office'];

        return round((float) DB::table('invoices as i')
            ->join('welfare_cases as c', 'c.id', '=', 'i.welfare_case_id')
            ->where('i.type', 'welfare')->where('i.status', '!=', 'cancelled')
            ->sum(DB::raw($office)), 2);
    }

    public function totals(): array
    {
        $account = $this->account();
        $sum = fn (string $type, string $direction) => round((float) OfficeTransaction::where('office_account_id', $account->id)
            ->where('type', $type)->where('direction', $direction)->sum('amount'), 2);

        $t = [
            'case_income' => $this->caseIncome(),
            'topups' => $sum('topup', 'credit'),
            'expenses' => $sum('expense', 'debit'),
            'reversals_in' => $sum('reversal', 'credit'),
            'reversals_out' => $sum('reversal', 'debit'),
        ];
        $t['balance'] = round($t['case_income'] + $t['topups'] + $t['reversals_in'] - $t['expenses'] - $t['reversals_out'], 2);

        return $t;
    }

    public function balance(): float
    {
        return $this->totals()['balance'];
    }

    public function topup(array $data): OfficeTransaction
    {
        return $this->post('topup', 'credit', $data, 'Top-up from '.$data['party']);
    }

    public function expense(array $data): OfficeTransaction
    {
        return $this->post('expense', 'debit', $data, 'Expense: '.$data['description']);
    }

    /** Correct a top-up or expense recorded in error by posting the opposite entry. */
    public function reverse(OfficeTransaction $original, string $reason): OfficeTransaction
    {
        if ($original->type === 'reversal') {
            throw ValidationException::withMessages(['reason' => 'A reversal cannot itself be reversed.']);
        }

        return $this->post('reversal', $original->direction === 'credit' ? 'debit' : 'credit', [
            'amount' => $original->amount,
            'transaction_date' => now()->toDateString(),
            'party' => $original->party,
            'category' => $original->category,
            'description' => 'Reversal of '.$original->reference.': '.$reason,
            'reverses_id' => $original->id,
        ], 'Reversal of '.$original->reference.': '.$reason);
    }

    private function post(string $type, string $direction, array $data, string $auditText): OfficeTransaction
    {
        return DB::transaction(function () use ($type, $direction, $data, $auditText) {
            // One office account row lock serialises every change, so two expenses can't overspend.
            $account = OfficeAccount::whereKey($this->account()->id)->lockForUpdate()->first();
            $amount = round((float) $data['amount'], 2);

            if (! empty($data['reverses_id']) && OfficeTransaction::where('reverses_id', $data['reverses_id'])->exists()) {
                throw ValidationException::withMessages(['reason' => 'This entry has already been reversed.']);
            }
            if ($direction === 'debit') {
                $balance = $this->balance();
                if ($amount > $balance) {
                    throw ValidationException::withMessages(['amount' => 'Not enough money in the office account. Available: '.money($balance).'.']);
                }
            }

            $tx = OfficeTransaction::create([
                'office_account_id' => $account->id,
                'reference' => 'OFT'.now()->format('ymd').strtoupper(Str::random(6)),
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'transaction_date' => $data['transaction_date'],
                'party' => $data['party'] ?? null,
                'category' => $data['category'] ?? null,
                'description' => mb_substr($data['description'] ?? $auditText, 0, 250),
                'external_ref' => $data['external_ref'] ?? null,
                'reverses_id' => $data['reverses_id'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            AuditLogger::log('office.'.$type, $tx, null, $tx->only(['reference', 'direction', 'amount', 'transaction_date', 'party', 'category', 'description']), $auditText);

            return $tx;
        });
    }

    /**
     * Statement lines (oldest first) with a running balance. Office income from cases
     * appears as one line per case per day, worked out from the members' payments.
     *
     * @return array{opening: float, rows: array<int, array>, closing: float}
     */
    public function statement(?string $from = null, ?string $to = null, ?string $type = null): array
    {
        $entries = [];
        foreach ($this->caseIncomeEntries() as $e) {
            $entries[] = $e;
        }
        $ledger = OfficeTransaction::with('recorder')->where('office_account_id', $this->account()->id)->orderBy('id')->get();
        foreach ($ledger as $t) {
            $entries[] = [
                'date' => $t->transaction_date->toDateString(),
                'sort' => $t->created_at?->format('His').sprintf('%08d', $t->id),
                'reference' => $t->reference,
                'type' => $t->type,
                'type_label' => $t->type_label,
                'details' => trim($t->description.($t->party && $t->type !== 'reversal' ? ' ('.$t->party.')' : '').($t->external_ref ? ' Ref '.$t->external_ref : '')),
                'category' => $t->category,
                'in' => $t->direction === 'credit' ? (float) $t->amount : 0.0,
                'out' => $t->direction === 'debit' ? (float) $t->amount : 0.0,
                'model' => $t,
                'by' => $t->recorder?->name,
            ];
        }
        usort($entries, fn ($a, $b) => [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']]);

        $opening = 0.0;
        $balance = 0.0;
        $rows = [];
        foreach ($entries as $e) {
            $balance = round($balance + $e['in'] - $e['out'], 2);
            if ($from && $e['date'] < $from) {
                $opening = $balance;
                continue;
            }
            if ($to && $e['date'] > $to) {
                continue;
            }
            $e['balance'] = $balance;
            if (! $type || $e['type'] === $type) {
                $rows[] = $e;
            }
        }
        $closing = $rows ? end($rows)['balance'] : $opening;

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $closing];
    }

    /** Office share of welfare payments, grouped by case and day. Refunds on cancelled cases show as money out. */
    private function caseIncomeEntries(): array
    {
        $txs = WalletTransaction::query()
            ->join('invoices as i', 'i.id', '=', 'wallet_transactions.invoice_id')
            ->join('welfare_cases as c', 'c.id', '=', 'i.welfare_case_id')
            ->where('i.type', 'welfare')->where('c.office_share', '>', 0)
            ->whereIn('wallet_transactions.type', ['contribution', 'refund'])
            ->orderBy('wallet_transactions.invoice_id')->orderBy('wallet_transactions.id')
            ->get(['wallet_transactions.invoice_id', 'wallet_transactions.direction', 'wallet_transactions.amount',
                'wallet_transactions.transaction_date', 'c.id as case_id', 'c.case_no', 'c.deceased_name', 'c.office_share']);

        $paidSoFar = [];
        $byCaseDay = [];
        foreach ($txs as $tx) {
            $before = $paidSoFar[$tx->invoice_id] ?? 0.0;
            $after = round($before + ($tx->direction === 'debit' ? 1 : -1) * (float) $tx->amount, 2);
            $paidSoFar[$tx->invoice_id] = $after;
            $share = (float) $tx->office_share;
            $delta = round(min(max($after, 0), $share) - min(max($before, 0), $share), 2);
            if ($delta == 0) {
                continue;
            }
            $day = \Illuminate\Support\Carbon::parse($tx->transaction_date)->toDateString();
            $key = $tx->case_id.'|'.$day.'|'.($delta > 0 ? 'in' : 'out');
            $byCaseDay[$key] ??= ['case_id' => $tx->case_id, 'case_no' => $tx->case_no, 'deceased' => $tx->deceased_name, 'date' => $day, 'amount' => 0.0, 'count' => 0];
            $byCaseDay[$key]['amount'] += $delta;
            $byCaseDay[$key]['count']++;
        }

        return array_values(array_map(fn ($g) => [
            'date' => $g['date'],
            'sort' => '000000'.sprintf('%08d', $g['case_id']),
            'reference' => $g['case_no'],
            'type' => 'case_income',
            'type_label' => 'Case income',
            'details' => $g['amount'] > 0
                ? 'Office share from case '.$g['case_no'].' ('.$g['deceased'].'), '.$g['count'].' payment(s)'
                : 'Office share returned: case '.$g['case_no'].' cancelled',
            'category' => null,
            'in' => $g['amount'] > 0 ? round($g['amount'], 2) : 0.0,
            'out' => $g['amount'] < 0 ? round(-$g['amount'], 2) : 0.0,
            'model' => null,
            'case_id' => $g['case_id'],
            'by' => null,
        ], $byCaseDay));
    }
}
