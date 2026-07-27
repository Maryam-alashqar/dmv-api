<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    //
    protected $fillable = [
    'user_id',
    'exam_id',
    'state_id',
    'start_time',
    'end_time',
    'score',
    'total_questions',
    'correct_answers',
    'incorrect_answers',
    'passed',
    'completion_status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'passed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(SimulationExam::class, 'exam_id');
        }

    public function state()
    {
        return $this->belongsTo(State::class);
        }

        public function answers()
        {
            return $this->hasMany(UserAnswer::class, 'attempt_id');
            }
    
}
