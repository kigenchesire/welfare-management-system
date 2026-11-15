<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Beneficiary extends Model
{
    use SoftDeletes;

    /** Category => [label, maximum allowed per member (null = unlimited)] */
    public const CATEGORIES = [
        'parent' => ['Parent', 2],
        'parent_in_law' => ['Parent-in-law', 2],
        'spouse' => ['Spouse', 1],
        'child' => ['Child', null],
    ];

    protected $fillable = ['member_id', 'name', 'category', 'is_deceased', 'date_of_death', 'created_by', 'updated_by'];

    protected $casts = ['is_deceased' => 'boolean', 'date_of_death' => 'date'];

    public function member() { return $this->belongsTo(Member::class)->withTrashed(); }

    public static function label(string $category): string
    {
        return self::CATEGORIES[$category][0] ?? ucfirst(str_replace('_', ' ', $category));
    }

    public static function limit(string $category): ?int
    {
        return self::CATEGORIES[$category][1] ?? null;
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::label($this->category);
    }
}
