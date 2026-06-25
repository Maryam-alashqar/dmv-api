<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
   protected $fillable = [
    'full_name',
    'email',
    'phone_number',
    'password',
    'profile_photo',
    'preferred_language',
    'selected_state_id',
    'verification_status',
    'account_status',
    'free_questions_used',
    'stripe_customer_id',
    'apple_uid',
    'google_uid',
    'role',
    'last_login',
];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function selectedState()
    {
        return $this->belongsTo(State::class, 'selected_state_id');
        }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
        }
}
