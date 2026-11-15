<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = ['member_id', 'phone', 'message', 'category', 'status', 'provider', 'provider_message_id', 'response', 'sent_by'];

    public function member() { return $this->belongsTo(Member::class)->withTrashed(); }
}
