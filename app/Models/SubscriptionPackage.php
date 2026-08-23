<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPackage extends Model
{
    //
    protected $fillable = [
    'name_ar',
    'name_en',
    'duration_days',
    'simulation_limit',
    'price_usd',
    'original_price_usd',
    'stripe_price_id',
    'stripe_product_id',
    'apple_product_id',
    'features',
    'is_active',
];

protected $casts = [
    'features' => 'array',
    'is_active' => 'boolean',
    'price_usd' => 'decimal:2',
    'original_price_usd' => 'decimal:2',
];

/**
 * True when an original (pre-discount) price is set and actually higher
 * than the current price — lets the mobile app show a "was $X" badge
 * without having to compare the two fields itself.
 */
protected function hasOffer(): Attribute
{
    return Attribute::make(
        get: fn () => $this->original_price_usd !== null && (float) $this->original_price_usd > (float) $this->price_usd,
    );
}

protected function discountPercent(): Attribute
{
    return Attribute::make(
        get: fn () => $this->has_offer
            ? (int) round((1 - ((float) $this->price_usd / (float) $this->original_price_usd)) * 100)
            : null,
    );
}
}
