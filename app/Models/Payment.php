<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'receipt_no', 'member_id', 'welfare_case_id', 'reference', 'amount', 'transaction_cost', 'payment_date', 'method', 'remarks', 'status',
        'recorded_by', 'reversed_by', 'reversed_at', 'reversal_reason',
    ];

    protected $casts = ['payment_date' => 'date', 'reversed_at' => 'datetime', 'amount' => 'decimal:2', 'transaction_cost' => 'decimal:2'];

    public function member() { return $this->belongsTo(Member::class)->withTrashed(); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by')->withTrashed(); }
    public function welfareCase() { return $this->belongsTo(WelfareCase::class); }
    public function transactions() { return $this->hasMany(WalletTransaction::class); }
}
