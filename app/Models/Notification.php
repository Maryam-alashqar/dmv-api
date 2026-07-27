<?php

namespace App\Models;

use App\Services\FcmPushService;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    //
    protected $fillable = [
    'user_id',
    'title_ar',
    'title_en',
    'message_ar',
    'message_en',
    'type',
    'read_status',
];

protected $casts = [
    'read_status' => 'boolean',
];

public function user()
{
    return $this->belongsTo(User::class);
}

/**
 * Every notification row is also a push-delivery attempt for its target
 * user (FR-43/FR-44), so this fires for admin broadcasts, subscription
 * reminders, and content-update alerts alike without each caller having
 * to remember to send the push itself. Best-effort: a missing device
 * token or FCM misconfiguration silently skips delivery, it never blocks
 * saving the in-app notification record.
 */
protected static function booted(): void
{
    static::created(function (Notification $notification) {
        if (! $notification->user_id) {
            return;
        }

        $user = $notification->user ?? User::find($notification->user_id);

        if (! $user || ! $user->fcm_token) {
            return;
        }

        app(FcmPushService::class)->send(
            $user->fcm_token,
            $notification->title_ar,
            $notification->message_ar,
            [
                'type' => $notification->type,
                'notification_id' => (string) $notification->id,
            ],
        );
    });
}
}
