<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WelfareCase extends Model
{
    public const CATEGORIES = [
        'self' => 'Self (Member)',
        'parent' => 'Parent',
        'parent_in_law' => 'Parent-in-law',
        'spouse' => 'Spouse',
        'child' => 'Child',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'Contributions in progress',
        'fully_collected' => 'Fully collected',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'case_no', 'member_id', 'beneficiary_id', 'category', 'deceased_name', 'bereavement_date', 'remarks',
        'contribution_amount', 'office_share', 'transport_share', 'status', 'members_invoiced', 'processed_at', 'created_by', 'closed_by',
        'closed_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'bereavement_date' => 'date',
        'processed_at' => 'datetime',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'contribution_amount' => 'decimal:2',
        'office_share' => 'decimal:2',
        'transport_share' => 'decimal:2',
    ];

    public function member() { return $this->belongsTo(Member::class)->withTrashed(); }
    public function beneficiary() { return $this->belongsTo(Beneficiary::class)->withTrashed(); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function disbursements() { return $this->hasMany(CaseDisbursement::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function getCategoryLabelAttribute(): string { return self::CATEGORIES[$this->category] ?? $this->category; }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? $this->status; }

    public function isActive(): bool { return ! in_array($this->status, ['closed', 'cancelled'], true); }

    /** Expected, collected and outstanding totals for this case (cancelled invoices excluded). */
    public function summary(): array
    {
        $row = $this->invoices()->where('status', '!=', 'cancelled')
            ->selectRaw("COUNT(*) c, COALESCE(SUM(amount),0) expected, COALESCE(SUM(amount_paid),0) collected,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END),0) paid_count")
            ->first();

        return [
            'invoices' => (int) $row->c,
            'paid_count' => (int) $row->paid_count,
            'expected' => (float) $row->expected,
            'collected' => (float) $row->collected,
            'outstanding' => round((float) $row->expected - (float) $row->collected, 2),
        ];
    }

    /**
     * How a member's payment on a case is shared out. The office share is taken
     * first, then transport; whatever remains goes to the welfare fund.
     * e.g. rate 600, office 100, transport 50: paid 600 -> 100/50/450; paid 120 -> 100/20/0.
     */
    public static function split(float $paid, float $office, float $transport): array
    {
        $o = round(min(max($paid, 0), $office), 2);
        $t = round(min(max($paid - $o, 0), $transport), 2);

        return ['office' => $o, 'transport' => $t, 'welfare' => round(max($paid, 0) - $o - $t, 2)];
    }

    /** SQL for the split of a column (amount or amount_paid) of invoices `i` joined to welfare_cases `c`. */
    public static function splitSql(string $col): array
    {
        $office = "LEAST(i.$col, c.office_share)";
        $transport = "LEAST(GREATEST(i.$col - c.office_share, 0), c.transport_share)";

        return ['office' => $office, 'transport' => $transport, 'welfare' => "(i.$col - $office - $transport)"];
    }

    /**
     * Office, transport and welfare money expected / collected / disbursed / still available,
     * for many cases at once (keyed by case id). Cancelled invoices are excluded (they were refunded).
     */
    public static function fundsFor(array $caseIds): array
    {
        if (! $caseIds) {
            return [];
        }
        $paid = self::splitSql('amount_paid');
        $due = self::splitSql('amount');
        $rows = \Illuminate\Support\Facades\DB::table('invoices as i')
            ->join('welfare_cases as c', 'c.id', '=', 'i.welfare_case_id')
            ->whereIn('i.welfare_case_id', $caseIds)->where('i.status', '!=', 'cancelled')
            ->groupBy('i.welfare_case_id')
            ->selectRaw("i.welfare_case_id id,
                SUM({$paid['office']}) office, SUM({$paid['transport']}) transport, SUM({$paid['welfare']}) welfare,
                SUM({$due['office']}) office_due, SUM({$due['transport']}) transport_due, SUM({$due['welfare']}) welfare_due")
            ->get()->keyBy('id');
        $out = CaseDisbursement::active()->whereIn('welfare_case_id', $caseIds)
            ->selectRaw('welfare_case_id, fund, SUM(amount) total')->groupBy('welfare_case_id', 'fund')->get()
            ->groupBy('welfare_case_id');

        $result = [];
        foreach ($caseIds as $id) {
            $r = $rows[$id] ?? null;
            $d = ($out[$id] ?? collect())->pluck('total', 'fund');
            $f = [
                'office' => round((float) ($r->office ?? 0), 2),
                'transport' => round((float) ($r->transport ?? 0), 2),
                'welfare' => round((float) ($r->welfare ?? 0), 2),
                'office_due' => round((float) ($r->office_due ?? 0), 2),
                'transport_due' => round((float) ($r->transport_due ?? 0), 2),
                'welfare_due' => round((float) ($r->welfare_due ?? 0), 2),
                'welfare_disbursed' => round((float) ($d['welfare'] ?? 0), 2),
                'transport_disbursed' => round((float) ($d['transport'] ?? 0), 2),
            ];
            $f['welfare_available'] = round($f['welfare'] - $f['welfare_disbursed'], 2);
            $f['transport_available'] = round($f['transport'] - $f['transport_disbursed'], 2);
            $result[$id] = $f;
        }

        return $result;
    }

    public function funds(): array
    {
        return self::fundsFor([$this->id])[$this->id];
    }
}
