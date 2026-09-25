<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AppleIapException;
use App\Http\Controllers\Controller;
use App\Models\AppleIapTransaction;
use App\Models\Payment;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\AppleAppStoreService;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

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
            'platform' => 'required|in:android,web,ios,desktop,other',
            // Client-generated key: sending the same value on a retry (timeout,
            // dropped connection, accidental double-tap) makes Stripe return the
            // original PaymentIntent instead of creating a second charge.
            'idempotency_key' => 'nullable|string|max:255',
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

            // Falls back to a key stable for one minute so an accidental double-tap
            // or an automatic client retry after a timeout can't double-charge the
            // user, while a genuinely new purchase attempt a minute later still
            // gets its own PaymentIntent.
            $idempotencyKey = $request->string('idempotency_key')->toString()
                ?: hash('sha256', "subscribe:{$user->id}:{$package->id}:".now()->format('YmdHi'));

            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => 'usd',

                'customer' => $user->stripe_customer_id,

                // Card/wallet payments only (matches the payment_method enum: no
                // bank-redirect methods are supported), so we don't need the mobile
                // app to implement a return_url / deep-link just to confirm a payment.
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],

                'metadata' => [
                    'user_id' => (string) $user->id,
                    'package_id' => (string) $package->id,
                    'platform' => $request->platform,
                ],

                'description' => 'DMV subscription: '.$package->name_en,
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            // Stripe returns the *same* PaymentIntent id for a retried idempotency
            // key, so keying on it here prevents a duplicate local Payment row too.
            $payment = Payment::firstOrCreate(
                ['stripe_payment_intent_id' => $paymentIntent->id],
                [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'amount_usd' => $package->price_usd,
                    'payment_method' => 'stripe',
                    'payment_status' => 'pending',
                ]
            );

            // Required by the Stripe Mobile Payment Sheet (iOS/Android SDKs) so it
            // can attach the confirmed payment method to the customer and offer
            // saved cards on future purchases; must be pinned to the API version
            // the calling SDK was built against.
            $stripeVersion = $request->header('Stripe-Version')
                ?: $request->string('stripe_version')->toString()
                ?: Stripe::getApiVersion();

            $ephemeralKey = $stripe->ephemeralKeys->create(
                ['customer' => $user->stripe_customer_id],
                ['stripe_version' => $stripeVersion]
            );

            return $this->successResponse([
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,

                'customer_id' => $user->stripe_customer_id,
                'ephemeral_key' => $ephemeralKey->secret,

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
        $request->user()->subscriptions()
            ->where('status', 'active')
            ->where('expiry_date', '<=', now())
            ->update(['status' => 'expired']);

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
                'product_id' => $subscription->package->apple_product_id,
                'environment' => $subscription->appleIapTransaction?->environment,

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

    /**
     * Stripe webhook receiver.
     * Confirms the payment and activates/extends the user's subscription only
     * after Stripe reports the PaymentIntent as actually succeeded — never on
     * the client's say-so.
     *
     * POST /api/subscriptions/stripe-webhook
     */
    public function stripeWebhook(Request $request)
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                $webhookSecret
            );
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'Invalid webhook payload.'], 400);
        }

        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event->data->object),
            default => null,
        };

        return response()->json(['success' => true]);
    }

    private function handlePaymentIntentSucceeded(PaymentIntent $paymentIntent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $payment || $payment->payment_status === 'confirmed') {
            return;
        }

        $payment->update([
            'payment_status' => 'confirmed',
            'payment_method' => $this->resolveStripePaymentMethod($paymentIntent),
        ]);

        $this->activateSubscription($payment);
    }

    private function handlePaymentIntentFailed(PaymentIntent $paymentIntent): void
    {
        Payment::where('stripe_payment_intent_id', $paymentIntent->id)
            ->where('payment_status', 'pending')
            ->update(['payment_status' => 'failed']);
    }

    /**
     * Activates the package for a confirmed payment. If the user already has
     * time remaining on an active subscription (an early renewal), the new
     * period is stacked on top of the current expiry instead of resetting it,
     * so paying early never wastes days already paid for (FR-21).
     */
    private function activateSubscription(Payment $payment): UserSubscription
    {
        $package = $payment->package;

        $existingActive = UserSubscription::where('user_id', $payment->user_id)
            ->where('status', 'active')
            ->where('expiry_date', '>', now())
            ->latest('expiry_date')
            ->first();

        $periodStart = $existingActive ? $existingActive->expiry_date : now();

        if ($existingActive) {
            $existingActive->update(['status' => 'expired']);
        }

        return UserSubscription::create([
            'user_id' => $payment->user_id,
            'package_id' => $package->id,
            'payment_id' => $payment->id,
            'activation_date' => now(),
            'expiry_date' => $periodStart->copy()->addDays($package->duration_days),
            'status' => 'active',
            'auto_renewal' => false,
        ]);
    }

    /**
     * Stripe reports the wallet used (Apple Pay / Google Pay) on the
     * PaymentMethod, not the PaymentIntent, so it takes a follow-up lookup.
     */
    private function resolveStripePaymentMethod(PaymentIntent $paymentIntent): string
    {
        if (! $paymentIntent->payment_method) {
            return 'credit_card';
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $method = $stripe->paymentMethods->retrieve($paymentIntent->payment_method);

            $wallet = $method->card->wallet->type ?? null;

            return match ($wallet) {
                'apple_pay' => 'apple_pay',
                'google_pay' => 'google_pay',
                default => ($method->card->funding ?? null) === 'debit' ? 'debit_card' : 'credit_card',
            };
        } catch (Throwable $exception) {
            report($exception);

            return 'credit_card';
        }
    }

    /**
     * Verifies an Apple In-App Purchase transaction and activates the
     * corresponding subscription for iOS purchases (BR-08).
     *
     * POST /api/subscriptions/apple-iap
     */
    public function appleIap(Request $request, AppleAppStoreService $apple)
    {
        $request->validate([
            'transaction_id' => ['required', 'string', 'max:255'],
            'product_id' => ['sometimes', 'string', 'max:255'],
        ]);

        $user = $request->user();

        try {
            $verified = $apple->verifyTransaction($request->string('transaction_id')->toString());
        } catch (AppleIapException $exception) {
            report($exception);

            return $this->errorResponse($exception->getMessage(), $exception->httpStatus);
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse('Unable to verify the Apple transaction at this time.', 503);
        }

        if ($request->filled('product_id') && ! hash_equals($verified->productId, $request->string('product_id')->toString())) {
            return $this->errorResponse('The verified Apple product does not match the requested product.', 422);
        }

        $package = SubscriptionPackage::query()
            ->where('apple_product_id', $verified->productId)
            ->where('is_active', true)
            ->first();

        if (! $package) {
            return $this->errorResponse('The verified Apple product is not configured or is unavailable.', 422);
        }

        if ($package->duration_days < 1 || $package->duration_days > 3660) {
            return $this->errorResponse('The Apple product duration is not configured correctly.', 500);
        }

        if ($verified->purchaseDate->isAfter(now()->addMinutes(5))) {
            return $this->errorResponse('The Apple purchase date is in the future.', 422);
        }

        if ($verified->appAccountToken !== null) {
            if ($user->apple_app_account_token === null
                || ! hash_equals(Str::lower($user->apple_app_account_token), $verified->appAccountToken)) {
                return $this->errorResponse('This Apple purchase belongs to a different app account.', 409);
            }
        }

        try {
            [$subscription, $alreadyProcessed] = DB::transaction(function () use ($user, $package, $verified) {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                $existingTransaction = AppleIapTransaction::query()
                    ->where('transaction_id', $verified->transactionId)
                    ->lockForUpdate()
                    ->first();

                if ($existingTransaction) {
                    if ($existingTransaction->user_id !== $user->id) {
                        throw new AppleIapException('This Apple purchase has already been linked to another user.', 409);
                    }

                    return [$existingTransaction->subscription()->firstOrFail(), true];
                }

                $existingActive = UserSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('expiry_date', '>', now())
                    ->latest('expiry_date')
                    ->lockForUpdate()
                    ->first();

                $periodStart = $verified->purchaseDate->copy();
                $canStack = $existingActive
                    && $verified->purchaseDate->greaterThanOrEqualTo($existingActive->activation_date)
                    && $verified->purchaseDate->lessThanOrEqualTo($existingActive->expiry_date);

                if ($canStack) {
                    $periodStart = $existingActive->expiry_date->copy();
                }

                $expiresDate = $periodStart->copy()->addDays($package->duration_days);
                $isActive = $expiresDate->isFuture();

                if ($isActive && $existingActive) {
                    $existingActive->update(['status' => 'expired']);
                }

                $payment = Payment::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'amount_usd' => $package->price_usd,
                    'payment_method' => 'apple_iap',
                    'apple_transaction_id' => $verified->transactionId,
                    'payment_status' => 'confirmed',
                ]);

                $subscription = UserSubscription::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'payment_id' => $payment->id,
                    'activation_date' => $verified->purchaseDate,
                    'expiry_date' => $expiresDate,
                    'status' => $isActive ? 'active' : 'expired',
                    'auto_renewal' => false,
                ]);

                AppleIapTransaction::create([
                    'user_id' => $user->id,
                    'payment_id' => $payment->id,
                    'user_subscription_id' => $subscription->id,
                    'product_id' => $verified->productId,
                    'transaction_id' => $verified->transactionId,
                    'original_transaction_id' => $verified->originalTransactionId,
                    'app_account_token' => $verified->appAccountToken,
                    'purchase_date' => $verified->purchaseDate,
                    'expires_date' => $expiresDate,
                    'environment' => $verified->environment,
                    'revocation_date' => $verified->revocationDate,
                    'signed_transaction_info' => $verified->signedTransactionInfo,
                    'verified_at' => now(),
                ]);

                return [$subscription, false];
            }, 3);
        } catch (AppleIapException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->httpStatus);
        } catch (QueryException $exception) {
            report($exception);

            $existing = AppleIapTransaction::where('transaction_id', $verified->transactionId)->first();
            if ($existing && $existing->user_id === $user->id && $existing->subscription) {
                $subscription = $existing->subscription;
                $alreadyProcessed = true;
            } else {
                return $this->errorResponse('This Apple purchase has already been processed.', 409);
            }
        }

        return $this->successResponse([
            'already_processed' => $alreadyProcessed,
            'transaction_id' => $verified->transactionId,
            'product_id' => $verified->productId,
            'environment' => $verified->environment,
            'expires_at' => $subscription->expiry_date,
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'activation_date' => $subscription->activation_date,
                'expiry_date' => $subscription->expiry_date,
            ],
        ], $alreadyProcessed ? 'Apple purchase was already processed.' : 'Apple subscription activated successfully.');
    }

    public function appleAccountToken(Request $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            if ($user->apple_app_account_token === null) {
                $user->forceFill(['apple_app_account_token' => (string) Str::uuid()])->save();
            }

            return $user;
        });

        // The iOS app reads data.token; app_account_token is kept for older builds.
        return $this->successResponse([
            'token' => $user->apple_app_account_token,
            'app_account_token' => $user->apple_app_account_token,
        ], 'Apple app account token retrieved successfully.');
    }
}
