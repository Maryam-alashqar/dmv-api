<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    //
    protected $fillable = [
    'user_id',
    'package_id',
    'amount_usd',
    'payment_method',
    'stripe_payment_intent_id',
    'apple_transaction_id',
    'payment_status',
];

public function user()
{
    return $this->belongsTo(User::class);
}

public function package()
{
    return $this->belongsTo(SubscriptionPackage::class, 'package_id');
}
}
