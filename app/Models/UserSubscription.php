<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    //
    protected $fillable = [
        'user_id',
        'package_id',
        'payment_id',
        'activation_date',
        'expiry_date',
        'status',
        'auto_renewal',
    ];

    protected $casts = [
        'activation_date' => 'datetime',
        'expiry_date' => 'datetime',
        'auto_renewal' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function appleIapTransaction()
    {
        return $this->hasOne(AppleIapTransaction::class);
    }
}
