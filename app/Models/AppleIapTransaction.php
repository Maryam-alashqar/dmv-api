<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppleIapTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'payment_id',
        'user_subscription_id',
        'product_id',
        'transaction_id',
        'original_transaction_id',
        'app_account_token',
        'purchase_date',
        'expires_date',
        'environment',
        'revocation_date',
        'signed_transaction_info',
        'verified_at',
    ];

    protected $hidden = [
        'signed_transaction_info',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'datetime',
            'expires_date' => 'datetime',
            'revocation_date' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function subscription()
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }
}
