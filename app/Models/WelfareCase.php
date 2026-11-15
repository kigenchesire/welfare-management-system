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
        'contribution_amount', 'status', 'members_invoiced', 'processed_at', 'created_by', 'closed_by',
        'closed_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'bereavement_date' => 'date',
        'processed_at' => 'datetime',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'contribution_amount' => 'decimal:2',
    ];

    public function member() { return $this->belongsTo(Member::class)->withTrashed(); }
    public function beneficiary() { return $this->belongsTo(Beneficiary::class)->withTrashed(); }
    public function invoices() { return $this->hasMany(Invoice::class); }
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
}
