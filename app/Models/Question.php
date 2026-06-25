<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    //
    protected $fillable = [
    'state_id',
    'category_id',
    'question_text_ar',
    'question_text_en',
    'question_type',
    'image_url',
    'option_a_ar',
    'option_b_ar',
    'option_c_ar',
    'option_d_ar',
    'option_a_en',
    'option_b_en',
    'option_c_en',
    'option_d_en',
    'correct_answer',
    'explanation_ar',
    'difficulty_level',
    'source_type',
    'import_status',
    'is_active',
];

public function state()
{
    return $this->belongsTo(State::class);
}

public function category()
{
    return $this->belongsTo(Category::class);
}
public function userAnswers()
{
    return $this->hasMany(UserAnswer::class);
}
}
