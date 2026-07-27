<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\UserSubscription;
use Illuminate\Console\Command;

/**
 * FR-43: push notifications for subscription expiry reminders,
 * 7 days and 1 day before expiry.
 */
class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'app:send-subscription-expiry-reminders';

    protected $description = 'Notify users whose subscription expires in 7 days or 1 day';

    public function handle(): int
    {
        $sent = 0;

        foreach ([7 => 'week', 1 => 'day'] as $daysAhead => $label) {
            $targetDate = today()->addDays($daysAhead);

            $subscriptions = UserSubscription::query()
                ->where('status', 'active')
                ->whereDate('expiry_date', $targetDate)
                ->with('user')
                ->get();

            foreach ($subscriptions as $subscription) {
                $user = $subscription->user;

                if (! $user) {
                    continue;
                }

                $alreadySentToday = Notification::query()
                    ->where('user_id', $user->id)
                    ->where('type', 'subscription_alert')
                    ->whereDate('created_at', today())
                    ->exists();

                if ($alreadySentToday) {
                    continue;
                }

                $message = $daysAhead === 1
                    ? 'اشتراكك سينتهي غداً. جدّد الآن للاستمرار بالوصول لكل الأسئلة واختبارات المحاكاة.'
                    : 'اشتراكك سينتهي خلال 7 أيام. جدّد الآن لتجنّب فقدان الوصول الكامل.';

                Notification::create([
                    'user_id' => $user->id,
                    'title_ar' => 'تذكير بانتهاء الاشتراك',
                    'message_ar' => $message,
                    'type' => 'subscription_alert',
                    'read_status' => false,
                ]);

                $sent++;
            }
        }

        $this->info("Sent {$sent} subscription expiry reminder(s).");

        return self::SUCCESS;
    }
}
