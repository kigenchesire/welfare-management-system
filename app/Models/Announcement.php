<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'sent_sms', 'recipients', 'created_by'];

    protected $casts = ['sent_sms' => 'boolean'];

    public function creator() { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
