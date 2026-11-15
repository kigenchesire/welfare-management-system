<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'pending' => 'Pending',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'inactive' => 'Inactive',
        'rejected' => 'Rejected',
    ];

    protected $fillable = [
        'member_no', 'user_id', 'full_name', 'national_id', 'phone', 'email', 'residential_address',
        'alternative_contact', 'registration_date', 'status', 'source', 'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason', 'status_reason',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function wallet() { return $this->hasOne(Wallet::class); }
    public function beneficiaries() { return $this->hasMany(Beneficiary::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function walletTransactions() { return $this->hasMany(WalletTransaction::class); }
    public function cases() { return $this->hasMany(WelfareCase::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }

    public function scopeActive($q) { return $q->where('status', 'active'); }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getBalanceAttribute(): float
    {
        return (float) ($this->wallet->balance ?? 0);
    }

    public function outstandingAmount(): float
    {
        return (float) $this->invoices()->open()->selectRaw('COALESCE(SUM(amount - amount_paid),0) as o')->value('o');
    }
}
