<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
    'option_a_image',
    'option_b_image',
    'option_c_image',
    'option_d_image',
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

/**
 * These columns store the relative path on the "public" disk (whatever
 * the admin form's FileUpload saved); resolving to a full URL here means
 * every consumer (mobile API response, admin panel) gets the same
 * ready-to-display URL, and the storage backend can move to S3/CDN later
 * (per the SRS) by changing only the disk config.
 */
protected function imageUrl(): Attribute
{
    return $this->diskUrlAttribute();
}

protected function optionAImage(): Attribute
{
    return $this->diskUrlAttribute();
}

protected function optionBImage(): Attribute
{
    return $this->diskUrlAttribute();
}

protected function optionCImage(): Attribute
{
    return $this->diskUrlAttribute();
}

protected function optionDImage(): Attribute
{
    return $this->diskUrlAttribute();
}

private function diskUrlAttribute(): Attribute
{
    return Attribute::make(
        get: fn (?string $value) => $value ? Storage::disk('public')->url($value) : null,
    );
}
}
