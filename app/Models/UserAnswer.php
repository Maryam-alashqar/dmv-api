<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAnswer extends Model
{
    //
    protected $fillable = [
    'attempt_id',
    'question_id',
    'selected_answer',
    'is_correct',
    'answered_at',
];

public function attempt()
{
    return $this->belongsTo(ExamAttempt::class, 'attempt_id');
}

public function question()
{
    return $this->belongsTo(Question::class);
}
}
