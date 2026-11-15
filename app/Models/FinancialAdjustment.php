<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialAdjustment extends Model
{
    protected $fillable = ['reference', 'member_id', 'direction', 'amount', 'reason', 'negative_balance_authorized', 'created_by'];

    protected $casts = ['amount' => 'decimal:2', 'negative_balance_authorized' => 'boolean'];

    public function member() { return $this->belongsTo(Member::class)->withTrashed(); }
    public function creator() { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
