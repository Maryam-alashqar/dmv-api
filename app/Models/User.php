<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use App\Models\UserSubscription;
use App\Models\UserQuestionView;
use App\Models\VerificationCode;
use App\Models\Payment;
use Filament\Panel;




class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;


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
    'profile_photo_url',
    'preferred_language',
    'selected_state_id',
    'verification_status',
    'account_status',
    'free_questions_used',
    'stripe_customer_id',
    'apple_uid',
    'google_uid',
    'fcm_token',
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

    /**
     * The column stores the file's relative path on the "public" disk;
     * this accessor resolves it to a full URL wherever the model is read
     * (API responses, Filament tables/forms), so every consumer gets the
     * same URL format regardless of who wrote the file (API or admin panel).
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Storage::disk('public')->url($value) : null,
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'admin';
        }

    public function getFilamentName(): string
{
    return $this->full_name ?: $this->email;
}
    public function selectedState()
    {
        return $this->belongsTo(State::class, 'selected_state_id');
        }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
        }

    public function subscriptions()
{
    return $this->hasMany(UserSubscription::class);
}

public function questionViews()
{
    return $this->hasMany(UserQuestionView::class);
}

public function hasActiveSubscription(): bool
{
    return $this->subscriptions()
        ->where('status', 'active')
        ->where('expiry_date', '>=', now())
        ->exists();
}

public function verificationCodes()
{
    return $this->hasMany(VerificationCode::class);
}
public function payments()
{
    return $this->hasMany(Payment::class);
}
}
