<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SubscriptionPackage;
use App\Models\UserSubscription;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use Throwable;

class SubscriptionController extends Controller
{
    use ApiResponseTrait;

    /**
     * إنشاء عملية دفع عبر Stripe.
     *
     * POST /api/subscriptions/initiate
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:subscription_packages,id',
            'platform' => 'required|in:android,web',
        ]);

        $user = $request->user();

        $package = SubscriptionPackage::where('id', $request->package_id)
            ->where('is_active', true)
            ->first();

        if (! $package) {
            return $this->errorResponse(
                'The selected subscription package is unavailable.',
                404
            );
        }

        $hasActiveSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('expiry_date', '>', now())
            ->exists();

        if ($hasActiveSubscription) {
            return $this->errorResponse(
                'You already have an active subscription.',
                422
            );
        }

        try {
            $stripe = new StripeClient(
                config('services.stripe.secret')
            );

            /*
             * Stripe يستقبل المبلغ بالسنت:
             * 4.99 USD = 499
             */
            $amountInCents = (int) round(
                ((float) $package->price_usd) * 100
            );

            if ($amountInCents < 50) {
                return $this->errorResponse(
                    'The package amount is invalid.',
                    422
                );
            }

            /*
             * ننشئ Stripe Customer أول مرة فقط،
             * ثم نخزن stripe_customer_id على المستخدم.
             */
            if (! $user->stripe_customer_id) {
                $customer = $stripe->customers->create([
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone_number,
                    'metadata' => [
                        'user_id' => (string) $user->id,
                    ],
                ]);

                $user->update([
                    'stripe_customer_id' => $customer->id,
                ]);
            }

            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => 'usd',

                'customer' => $user->stripe_customer_id,

                'automatic_payment_methods' => [
                    'enabled' => true,
                ],

                'metadata' => [
                    'user_id' => (string) $user->id,
                    'package_id' => (string) $package->id,
                    'platform' => $request->platform,
                ],

                'description' => 'DMV subscription: ' . $package->name_en,
            ]);

            $payment = Payment::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'amount_usd' => $package->price_usd,
                'payment_method' => 'stripe',
                'stripe_payment_intent_id' => $paymentIntent->id,
                'payment_status' => 'pending',
            ]);

            return $this->successResponse([
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,

                'package' => [
                    'id' => $package->id,
                    'name_ar' => $package->name_ar,
                    'name_en' => $package->name_en,
                    'price_usd' => $package->price_usd,
                    'duration_days' => $package->duration_days,
                ],

                'currency' => 'usd',
                'publishable_key' => config('services.stripe.key'),
            ], 'Payment initiated successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse(
                'Unable to initiate payment at this time.',
                500
            );
        }
    }

    /**
     * حالة الاشتراك الحالي.
     *
     * GET /api/subscriptions/status
     */
    public function status(Request $request)
    {
        $subscription = $request->user()
            ->subscriptions()
            ->with(['package', 'payment'])
            ->where('status', 'active')
            ->where('expiry_date', '>', now())
            ->latest('expiry_date')
            ->first();

        if (! $subscription) {
            return $this->successResponse([
                'has_active_subscription' => false,
                'subscription' => null,
            ], 'No active subscription found.');
        }

        return $this->successResponse([
            'has_active_subscription' => true,

            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'activation_date' => $subscription->activation_date,
                'expiry_date' => $subscription->expiry_date,
                'remaining_days' => now()->diffInDays(
                    $subscription->expiry_date,
                    false
                ),
                'auto_renewal' => $subscription->auto_renewal,

                'package' => [
                    'id' => $subscription->package->id,
                    'name_ar' => $subscription->package->name_ar,
                    'name_en' => $subscription->package->name_en,
                    'price_usd' => $subscription->package->price_usd,
                    'duration_days' => $subscription->package->duration_days,
                    'features' => $subscription->package->features,
                ],
            ],
        ], 'Subscription status retrieved successfully.');
    }

    /**
     * سجل الاشتراكات والمدفوعات.
     *
     * GET /api/subscriptions/history
     */
    public function history(Request $request)
    {
        $subscriptions = UserSubscription::where(
            'user_id',
            $request->user()->id
        )
            ->with(['package', 'payment'])
            ->latest()
            ->paginate(
                $request->integer('per_page', 15)
            );

        return $this->successResponse(
            $subscriptions,
            'Subscription history retrieved successfully.'
        );
    }

    public function paymentHistory(Request $request)
{
    $payments = Payment::with('package')
        ->where('user_id', $request->user()->id)
        ->latest()
        ->paginate(10);

    return response()->json([
        'success' => true,
        'message' => 'Payment history retrieved successfully',
        'data' => $payments,
    ]);
}
}
