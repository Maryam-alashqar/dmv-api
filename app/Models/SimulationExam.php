<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationExam extends Model
{
    //
    protected $fillable = [
    'title_ar',
    'state_id',
    'total_questions',
    'passing_score',
    'is_published',
];

protected $casts = [
    'is_published' => 'boolean',
];

public function state()
{
    return $this->belongsTo(State::class);
}
}
