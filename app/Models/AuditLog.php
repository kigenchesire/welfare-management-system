<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'user_name', 'action', 'auditable_type', 'auditable_id', 'description',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit records are read-only.'));
        static::deleting(fn () => throw new LogicException('Audit records are read-only.'));
    }

    public function user() { return $this->belongsTo(User::class)->withTrashed(); }

    public function getSubjectLabelAttribute(): string
    {
        return $this->auditable_type ? class_basename($this->auditable_type).' #'.$this->auditable_id : '-';
    }
}
