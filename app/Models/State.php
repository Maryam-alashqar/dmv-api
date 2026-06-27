<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $fillable = [
        'name_en',
        'name_ar',
        'abbreviation',
        'dmv_question_count',
        'dmv_passing_score',
        'icon_url',
        'is_active',
        ];

        public function categories()
        {
            return $this->hasMany(Category::class);
            }
        public function questions()
{
    return $this->hasMany(Question::class);
}
        public function simulationExams()
        {
            return $this->hasMany(SimulationExam::class);
            }
}
