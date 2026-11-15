<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The balance column is a running total. It is only ever changed by
 * App\Services\WalletService, which writes a matching WalletTransaction row.
 */
class Wallet extends Model
{
    protected $fillable = ['member_id', 'balance'];

    protected $casts = ['balance' => 'decimal:2'];

    public function member() { return $this->belongsTo(Member::class)->withTrashed(); }
    public function transactions() { return $this->hasMany(WalletTransaction::class); }
}
