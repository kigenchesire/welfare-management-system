<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeAccount extends Model
{
    protected $fillable = ['name', 'account_no'];

    public function transactions() { return $this->hasMany(OfficeTransaction::class); }
}
