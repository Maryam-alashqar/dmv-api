<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPackage extends Model
{
    //
    protected $fillable = [
    'name_ar',
    'name_en',
    'duration_days',
    'price_usd',
    'stripe_price_id',
    'apple_product_id',
    'features',
    'is_active',
];

protected $casts = [
    'features' => 'array',
    'is_active' => 'boolean',
];
}
