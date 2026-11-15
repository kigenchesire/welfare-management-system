<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class WalletTransaction extends Model
{
    public const TYPES = [
        'deposit' => 'Deposit',
        'contribution' => 'Welfare contribution',
        'invoice_payment' => 'Invoice payment',
        'refund' => 'Refund',
        'adjustment' => 'Adjustment',
        'reversal' => 'Reversal',
    ];

    protected $fillable = [
        'wallet_id', 'member_id', 'reference', 'type', 'direction', 'amount', 'balance_before', 'balance_after',
        'description', 'invoice_id', 'payment_id', 'adjustment_id', 'recorded_by', 'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The ledger is append-only: mistakes are corrected with reversal/adjustment entries.
        static::updating(fn () => throw new LogicException('Wallet transactions cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Wallet transactions cannot be deleted.'));
    }

    public function member() { return $this->belongsTo(Member::class)->withTrashed(); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by')->withTrashed(); }

    public function getTypeLabelAttribute(): string { return self::TYPES[$this->type] ?? $this->type; }
}
