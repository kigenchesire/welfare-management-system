<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteImage extends Model
{
    protected $fillable = ['path', 'caption', 'sort_order', 'is_visible', 'uploaded_by'];

    protected $casts = ['is_visible' => 'boolean'];
}
