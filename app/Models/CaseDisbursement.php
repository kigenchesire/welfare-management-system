<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseDisbursement extends Model
{
    public const FUNDS = [
        'welfare' => 'Welfare fund',
        'transport' => 'Transport',
    ];

    protected $fillable = [
        'reference', 'welfare_case_id', 'fund', 'recipient', 'recipient_phone', 'amount', 'disbursement_date',
        'payment_ref', 'remarks', 'status', 'recorded_by', 'voided_by', 'voided_at', 'void_reason',
    ];

    protected $casts = ['amount' => 'decimal:2', 'disbursement_date' => 'date', 'voided_at' => 'datetime'];

    public function welfareCase() { return $this->belongsTo(WelfareCase::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by')->withTrashed(); }
    public function voider() { return $this->belongsTo(User::class, 'voided_by')->withTrashed(); }

    public function scopeActive($q) { return $q->where('status', 'active'); }

    public function getFundLabelAttribute(): string { return self::FUNDS[$this->fund] ?? $this->fund; }
}
