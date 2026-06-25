<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    //
    protected $fillable = [
    'state_id',
    'name_ar',
    'name_en',
    'category_type',
    'sequence_order',
];

public function state()
{
    return $this->belongsTo(State::class);
}

public function questions()
{
    return $this->hasMany(Question::class);
}
}
