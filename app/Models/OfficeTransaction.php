<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class OfficeTransaction extends Model
{
    public const TYPES = [
        'topup' => 'Top-up',
        'expense' => 'Expense',
        'reversal' => 'Reversal',
    ];

    protected $fillable = [
        'office_account_id', 'reference', 'type', 'direction', 'amount', 'transaction_date', 'party', 'category',
        'description', 'external_ref', 'reverses_id', 'recorded_by',
    ];

    protected $casts = ['amount' => 'decimal:2', 'transaction_date' => 'date'];

    protected static function booted(): void
    {
        // Append-only: a mistake is corrected with a reversal entry, never by editing or deleting.
        static::updating(fn () => throw new LogicException('Office transactions cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Office transactions cannot be deleted.'));
    }

    public function account() { return $this->belongsTo(OfficeAccount::class, 'office_account_id'); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by')->withTrashed(); }
    public function reverses() { return $this->belongsTo(self::class, 'reverses_id'); }
    public function reversal() { return $this->hasOne(self::class, 'reverses_id'); }

    public function getTypeLabelAttribute(): string { return self::TYPES[$this->type] ?? $this->type; }
}
