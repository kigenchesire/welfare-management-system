<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    public const STATUSES = [
        'unpaid' => 'Unpaid',
        'partially_paid' => 'Partially paid',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ];

    public const CUSTOM_CATEGORIES = [
        'airtime' => 'Airtime',
        'accessories' => 'Accessories',
        'lunch' => 'Lunch',
        'welfare_activity' => 'Welfare activity',
        'special_contribution' => 'Special contribution',
        'other' => 'Other',
    ];

    protected $fillable = [
        'invoice_no', 'member_id', 'welfare_case_id', 'type', 'category', 'title', 'description', 'amount',
        'amount_paid', 'invoice_date', 'due_date', 'status', 'remarks', 'batch_ref', 'created_by',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'cancelled_at' => 'datetime',
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function member() { return $this->belongsTo(Member::class)->withTrashed(); }
    public function welfareCase() { return $this->belongsTo(WelfareCase::class); }
    public function transactions() { return $this->hasMany(WalletTransaction::class); }

    /** Invoices that still have money owing. */
    public function scopeOpen($q) { return $q->whereIn('status', ['unpaid', 'partially_paid']); }

    public function getOutstandingAttribute(): float
    {
        return $this->status === 'cancelled' ? 0.0 : round((float) $this->amount - (float) $this->amount_paid, 2);
    }

    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? $this->status; }

    public function getCategoryLabelAttribute(): string
    {
        if ($this->type === 'welfare') {
            return WelfareCase::CATEGORIES[$this->category] ?? (string) $this->category;
        }

        return self::CUSTOM_CATEGORIES[$this->category] ?? (string) $this->category;
    }

    public function refreshStatus(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }
        $paid = round((float) $this->amount_paid, 2);
        $this->status = $paid <= 0 ? 'unpaid' : ($paid >= round((float) $this->amount, 2) ? 'paid' : 'partially_paid');
    }
}
