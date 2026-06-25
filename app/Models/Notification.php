<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    //
    protected $fillable = [
    'user_id',
    'title_ar',
    'title_en',
    'message_ar',
    'message_en',
    'type',
    'read_status',
];

protected $casts = [
    'read_status' => 'boolean',
];

public function user()
{
    return $this->belongsTo(User::class);
}
}
